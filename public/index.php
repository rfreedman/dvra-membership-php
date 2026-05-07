<?php

declare(strict_types=1);

use DvraMembership\Repository\AdminAccountRepository;
use DvraMembership\Repository\DuplicateMemberKeyNumber;
use DvraMembership\Repository\MemberListRepository;
use DvraMembership\Repository\MemberRepository;
use DvraMembership\Repository\PaymentRepository;
use DvraMembership\Repository\ReferenceDataRepository;
use DvraMembership\Repository\PaymentsReportRepository;
use DvraMembership\Repository\ReportsRepository;
use DvraMembership\Support\AdminSeed;
use DvraMembership\Support\MemberExport;
use DvraMembership\Support\MemberInputNormalizer;
use DvraMembership\Support\KeyholdersReportParams;
use DvraMembership\Support\MemberListExportParams;
use DvraMembership\Support\PaymentsReportExport;
use DvraMembership\Support\PaymentsReportExportParams;
use DvraMembership\Support\StaticRosterExports;
use DvraMembership\Support\PdoFactory;
use DvraMembership\Support\Schema;
use DvraMembership\Support\Settings;
use DvraMembership\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PHP dependencies missing. Run: cd php && composer install\n";
    exit;
}

require $autoload;

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$root = dirname(__DIR__);
$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0775, true);
}

$settings = new Settings();
$basePath = $settings->get('BASE_PATH');
$urlBase = MemberListRepository::normalizedBase($basePath);
$pdo = PdoFactory::create($settings);
Schema::ensure($pdo);
AdminSeed::ensureBootstrapAdmin($pdo, $settings);

$memberListRepo = new MemberListRepository($pdo);
$paymentsReportRepo = new PaymentsReportRepository($pdo);
$reportsRepo = new ReportsRepository($pdo);
$referenceDataRepo = new ReferenceDataRepository($pdo);
$adminAccountRepo = new AdminAccountRepository($pdo);

$app = AppFactory::create();
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$wrapHtml = static function (string $html, string $title, array $extras = []): string {
    $contentHtml = $html;
    return View::render('layout', array_merge([
        'title' => $title,
        'contentHtml' => $contentHtml,
    ], $extras));
};

$htmlResponse = static function (Response $response, string $body): Response {
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
};

$authenticated = static fn (): bool => isset($_SESSION['admin_id']);

$authMiddleware = static function (Request $request, RequestHandler $handler) use ($urlBase): Response {
    if (!isset($_SESSION['admin_id'])) {
        $loc = ($urlBase === '' ? '' : $urlBase) . '/login';

        return (new SlimResponse(302))->withHeader('Location', $loc);
    }

    return $handler->handle($request);
};

$adminPostRedirectResponse = static function (Response $response, ?string $errorMsg = null) use ($urlBase): Response {
    $loc = ($urlBase === '' ? '' : $urlBase) . '/admin';
    if ($errorMsg !== null && $errorMsg !== '') {
        $loc .= '?error=' . rawurlencode($errorMsg);
    }

    return $response->withStatus(303)->withHeader('Location', $loc);
};

$referenceWriteErrorFromPdo = static function (\PDOException $e): ?string {
    $msg = $e->getMessage();
    if (str_contains($msg, 'UNIQUE constraint')) {
        return 'That name already exists.';
    }
    if (str_contains($msg, 'FOREIGN KEY constraint')) {
        return 'Cannot delete item while records still reference it.';
    }

    return null;
};

$app->get('/health', function (Request $request, Response $response): Response {
    $response->getBody()->write('OK');
    return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
});

$app->get('/login', function (Request $request, Response $response) use ($wrapHtml, $htmlResponse, $urlBase): Response {
    if (isset($_SESSION['admin_id'])) {
        $loc = ($urlBase === '' ? '' : $urlBase) . '/';

        return $response->withHeader('Location', $loc)->withStatus(302);
    }
    $body = $wrapHtml(View::render('login', ['error' => null, 'base' => $urlBase]), 'Login', []);

    return $htmlResponse($response, $body);
});

$app->post('/login', function (Request $request, Response $response) use ($pdo, $wrapHtml, $htmlResponse, $authenticated, $urlBase): Response {
    if ($authenticated()) {
        $loc = ($urlBase === '' ? '' : $urlBase) . '/';

        return $response->withHeader('Location', $loc)->withStatus(302);
    }
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $user = isset($data['username']) ? trim((string) $data['username']) : '';
    $pass = isset($data['password']) ? (string) $data['password'] : '';
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $row['id'];
        $loc = ($urlBase === '' ? '' : $urlBase) . '/';

        return $response->withHeader('Location', $loc)->withStatus(303);
    }
    $body = $wrapHtml(
        View::render('login', ['error' => 'Invalid username or password.', 'base' => $urlBase]),
        'Login',
        []
    );
    return $htmlResponse($response, $body);
});

$app->post('/logout', function (Request $request, Response $response): Response {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', \time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), $p['httponly']);
    }
    session_destroy();
    $loc = ($urlBase === '' ? '' : $urlBase) . '/login';

    return $response->withHeader('Location', $loc)->withStatus(303);
});

$tabulatorCss = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.2/dist/css/tabulator.min.css">';

$app->post('/', function (Request $request, Response $response) use ($urlBase): Response {
    $flat = MemberListExportParams::mergedFlatAfterMemberListPost($request->getParsedBody() ?? []);
    $params = MemberListRepository::parseListQuery($flat);
    MemberListExportParams::persistFromParsed($params);
    $loc = ($urlBase === '' ? '' : $urlBase) . '/';

    return $response->withStatus(303)->withHeader('Location', $loc);
})->add($authMiddleware);

$app->get('/', function (Request $request, Response $response) use ($memberListRepo, $wrapHtml, $htmlResponse, $urlBase, $tabulatorCss): Response {
    unset($request);
    $params = MemberListRepository::parseListQuery(MemberListExportParams::persistedMembersListQueryInput());
    MemberListExportParams::persistFromParsed($params);
    $filter = [
        'search' => $params['search'],
        'membership_type_id' => $params['membership_type_id'],
        'arrl' => $params['arrl'],
        'current_only' => $params['current_only'],
    ];
    $total = $memberListRepo->countMembers($filter);
    $tabulatorRows = $memberListRepo->listRowsForTabulator(array_merge($filter, [
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
    ]), $urlBase);
    $membersJson = MemberListRepository::tabulatorJsonFromRows($tabulatorRows);

    $exportQuery = '';
    $membersSortTouchUrl = ($urlBase === '' ? '' : $urlBase) . '/members/session-touch';

    $inner = View::render('members', [
        'total' => $total,
        'search' => $params['search'],
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
        'membership_type_id' => $params['membership_type_id'],
        'arrl' => $params['arrl'],
        'current_only' => $params['current_only'],
        'membership_types' => $memberListRepo->listMembershipTypes(),
        'exportQuery' => $exportQuery,
        'base' => $urlBase,
    ]);
    $scripts = View::render('members_tabulator_scripts', [
        'membersJson' => $membersJson,
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
        'base' => $urlBase,
        'membersSortTouchUrl' => $membersSortTouchUrl,
    ]);
    $body = $wrapHtml($inner, 'Members', [
        'authenticated' => true,
        'activeNav' => 'members',
        'base' => $urlBase,
        'extraHeadHtml' => $tabulatorCss,
        'extraScriptsHtml' => $scripts,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->get('/admin', function (Request $request, Response $response) use (
    $referenceDataRepo,
    $adminAccountRepo,
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    $qp = $request->getQueryParams();
    $error = isset($qp['error']) ? trim((string) $qp['error']) : '';
    $errorOut = $error !== '' ? $error : null;

    $inner = View::render('admin', [
        'base' => $urlBase,
        'error' => $errorOut,
        'license_classes' => $referenceDataRepo->listLicenseClasses(),
        'membership_types' => $referenceDataRepo->listMembershipTypes(),
        'admin_users' => $adminAccountRepo->listAdminUsers(),
        'managers' => $adminAccountRepo->listManagers(),
    ]);

    return $htmlResponse($response, $wrapHtml($inner, 'Admin', [
        'authenticated' => true,
        'activeNav' => 'admin',
        'base' => $urlBase,
    ]));
})->add($authMiddleware);

$app->post('/admin/license/create', function (Request $request, Response $response) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $name = isset($data['name']) ? trim((string) $data['name']) : '';
    if ($name === '') {
        return $adminPostRedirectResponse($response, 'Name is required.');
    }
    try {
        $referenceDataRepo->createLicenseClass($name);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admin/license/{license_id}/update', function (Request $request, Response $response, array $args) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    $id = (int) ($args['license_id'] ?? 0);
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $name = isset($data['name']) ? trim((string) $data['name']) : '';
    if ($id <= 0 || $name === '') {
        return $adminPostRedirectResponse($response, 'Invalid license class.');
    }
    try {
        $referenceDataRepo->updateLicenseClass($id, $name);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admin/license/{license_id}/delete', function (Request $request, Response $response, array $args) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    unset($request);
    $id = (int) ($args['license_id'] ?? 0);
    if ($id <= 0) {
        return $adminPostRedirectResponse($response, 'Invalid license class.');
    }
    try {
        $referenceDataRepo->deleteLicenseClass($id);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admin/membership-type/create', function (Request $request, Response $response) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $name = isset($data['name']) ? trim((string) $data['name']) : '';
    if ($name === '') {
        return $adminPostRedirectResponse($response, 'Name is required.');
    }
    try {
        $referenceDataRepo->createMembershipType($name);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admin/membership-type/{type_id}/update', function (Request $request, Response $response, array $args) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    $id = (int) ($args['type_id'] ?? 0);
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $name = isset($data['name']) ? trim((string) $data['name']) : '';
    if ($id <= 0 || $name === '') {
        return $adminPostRedirectResponse($response, 'Invalid membership type.');
    }
    try {
        $referenceDataRepo->updateMembershipType($id, $name);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admin/membership-type/{type_id}/delete', function (Request $request, Response $response, array $args) use (
    $referenceDataRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    unset($request);
    $id = (int) ($args['type_id'] ?? 0);
    if ($id <= 0) {
        return $adminPostRedirectResponse($response, 'Invalid membership type.');
    }
    try {
        $referenceDataRepo->deleteMembershipTypeOrFail($id);
    } catch (\RuntimeException $e) {
        return $adminPostRedirectResponse($response, $e->getMessage());
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admins/create', function (Request $request, Response $response) use (
    $adminAccountRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $username = isset($data['username']) ? trim((string) $data['username']) : '';
    $password = isset($data['password']) ? (string) $data['password'] : '';
    if ($username === '' || trim($password) === '') {
        return $adminPostRedirectResponse($response, 'Username and password are required.');
    }
    try {
        $adminAccountRepo->createAdminUser($username, $password);
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'admin_users')) {
            return $adminPostRedirectResponse($response, 'That administrator username already exists.');
        }
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admins/{admin_id}/password', function (Request $request, Response $response, array $args) use (
    $adminAccountRepo,
    $adminPostRedirectResponse
): Response {
    $id = (int) ($args['admin_id'] ?? 0);
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $password = isset($data['password']) ? (string) $data['password'] : '';
    if ($id <= 0 || trim($password) === '') {
        return $adminPostRedirectResponse($response, 'Invalid administrator or password.');
    }
    $adminAccountRepo->updateAdminPassword($id, $password);

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/admins/{admin_id}/delete', function (Request $request, Response $response, array $args) use (
    $adminAccountRepo,
    $adminPostRedirectResponse
): Response {
    unset($request);
    $id = (int) ($args['admin_id'] ?? 0);
    if ($id <= 0) {
        return $adminPostRedirectResponse($response, 'Invalid administrator.');
    }
    try {
        $adminAccountRepo->deleteAdminUserOrFail($id);
    } catch (\RuntimeException $e) {
        return $adminPostRedirectResponse($response, $e->getMessage());
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/managers/create', function (Request $request, Response $response) use (
    $adminAccountRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $username = isset($data['username']) ? trim((string) $data['username']) : '';
    $password = isset($data['password']) ? (string) $data['password'] : '';
    $displayRaw = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
    $displayName = $displayRaw !== '' ? $displayRaw : null;
    if ($username === '' || trim($password) === '') {
        return $adminPostRedirectResponse($response, 'Username and password are required.');
    }
    try {
        $adminAccountRepo->createManager($username, $password, $displayName);
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'managers')) {
            return $adminPostRedirectResponse($response, 'That manager username already exists.');
        }
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/managers/{manager_id}/password', function (Request $request, Response $response, array $args) use (
    $adminAccountRepo,
    $adminPostRedirectResponse
): Response {
    $id = (int) ($args['manager_id'] ?? 0);
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $password = isset($data['password']) ? (string) $data['password'] : '';
    if ($id <= 0 || trim($password) === '') {
        return $adminPostRedirectResponse($response, 'Invalid manager or password.');
    }
    $adminAccountRepo->updateManagerPassword($id, $password);

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/managers/{manager_id}/profile', function (Request $request, Response $response, array $args) use (
    $adminAccountRepo,
    $adminPostRedirectResponse
): Response {
    $id = (int) ($args['manager_id'] ?? 0);
    /** @var array<string, mixed> $data */
    $data = $request->getParsedBody() ?? [];
    $displayRaw = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
    $displayName = $displayRaw !== '' ? $displayRaw : null;
    if ($id <= 0) {
        return $adminPostRedirectResponse($response, 'Invalid manager.');
    }
    $adminAccountRepo->updateManagerProfile($id, $displayName);

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$app->post('/managers/{manager_id}/delete', function (Request $request, Response $response, array $args) use (
    $adminAccountRepo,
    $adminPostRedirectResponse,
    $referenceWriteErrorFromPdo
): Response {
    unset($request);
    $id = (int) ($args['manager_id'] ?? 0);
    if ($id <= 0) {
        return $adminPostRedirectResponse($response, 'Invalid manager.');
    }
    try {
        $adminAccountRepo->deleteManager($id);
    } catch (\PDOException $e) {
        $msg = $referenceWriteErrorFromPdo($e);
        if ($msg !== null) {
            return $adminPostRedirectResponse($response, $msg);
        }
        throw $e;
    }

    return $adminPostRedirectResponse($response);
})->add($authMiddleware);

$sendMemberExport = static function (Response $response, string $format, string $payload): Response {
    $stem = MemberExport::fileStem();
    [$contentType, $ext] = match ($format) {
        'csv' => ['text/csv; charset=utf-8', 'csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'pdf' => ['application/pdf', 'pdf'],
        default => ['application/octet-stream', 'bin'],
    };
    $name = "{$stem}.{$ext}";
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', $contentType)
        ->withHeader('Content-Disposition', 'attachment; filename="' . str_replace(['"', '\\'], '', $name) . '"');
};

$runMemberExport = static function (string $format) use ($memberListRepo, $sendMemberExport): callable {
    return function (Request $request, Response $response) use (
        $memberListRepo,
        $format,
        $sendMemberExport
    ): Response {
        $params = MemberListExportParams::resolveForExport($request);
        $rows = $memberListRepo->listRowsForExport($params);

        $binary = match ($format) {
            'csv' => MemberExport::toCsvBinary($rows),
            'xlsx' => MemberExport::toXlsxBinary($rows),
            'pdf' => MemberExport::toPdfBinary($rows),
            default => '',
        };

        return $binary !== ''
            ? $sendMemberExport($response, $format, $binary)
            : $response->withStatus(404);
    };
};

$app->get('/members/export.csv', $runMemberExport('csv'))->add($authMiddleware);
$app->get('/members/export.xlsx', $runMemberExport('xlsx'))->add($authMiddleware);
$app->get('/members/export.pdf', $runMemberExport('pdf'))->add($authMiddleware);

$app->post('/members/session-touch', function (Request $request, Response $response): Response {
    MemberListExportParams::mergeClientSortIntoSession($request->getParsedBody() ?? []);

    return $response->withStatus(204);
})->add($authMiddleware);

$sendPaymentsReportExport = static function (Response $response, string $format, string $payload): Response {
    $stem = PaymentsReportExport::fileStem();
    [$contentType, $ext] = match ($format) {
        'csv' => ['text/csv; charset=utf-8', 'csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'pdf' => ['application/pdf', 'pdf'],
        default => ['application/octet-stream', 'bin'],
    };
    $name = "{$stem}.{$ext}";
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', $contentType)
        ->withHeader('Content-Disposition', 'attachment; filename="' . str_replace(['"', '\\'], '', $name) . '"');
};

$runPaymentsReportExport = static function (string $format) use ($paymentsReportRepo, $sendPaymentsReportExport): callable {
    return function (Request $request, Response $response) use (
        $paymentsReportRepo,
        $format,
        $sendPaymentsReportExport
    ): Response {
        $params = PaymentsReportExportParams::resolveForExport($request);
        $rows = $paymentsReportRepo->listPaymentReportRows($params);

        $binary = match ($format) {
            'csv' => PaymentsReportExport::toCsvBinary($rows),
            'xlsx' => PaymentsReportExport::toXlsxBinary($rows),
            'pdf' => PaymentsReportExport::toPdfBinary($rows),
            default => '',
        };

        return $binary !== ''
            ? $sendPaymentsReportExport($response, $format, $binary)
            : $response->withStatus(404);
    };
};

$sendNamedReportExport = static function (Response $response, string $stem, string $format, string $payload): Response {
    [$contentType, $ext] = match ($format) {
        'csv' => ['text/csv; charset=utf-8', 'csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'pdf' => ['application/pdf', 'pdf'],
        default => ['application/octet-stream', 'bin'],
    };
    $name = "{$stem}.{$ext}";
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', $contentType)
        ->withHeader('Content-Disposition', 'attachment; filename="' . str_replace(['"', '\\'], '', $name) . '"');
};

$app->get('/reports', function (Request $request, Response $response) use (
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    $body = $wrapHtml(View::render('reports', ['base' => $urlBase]), 'Reports', [
        'authenticated' => true,
        'activeNav' => 'reports',
        'base' => $urlBase,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->post('/reports/payments', function (Request $request, Response $response) use ($urlBase): Response {
    $flat = PaymentsReportExportParams::mergedFlatAfterPaymentReportPost($request->getParsedBody() ?? []);
    $params = PaymentsReportRepository::parsePaymentReportQuery($flat);
    PaymentsReportExportParams::persistFromParsed($params);
    $loc = ($urlBase === '' ? '' : $urlBase) . '/reports/payments';

    return $response->withStatus(303)->withHeader('Location', $loc);
})->add($authMiddleware);

$app->get('/reports/payments', function (Request $request, Response $response) use (
    $paymentsReportRepo,
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    unset($request);
    $params = PaymentsReportRepository::parsePaymentReportQuery(
        PaymentsReportExportParams::persistedPaymentReportQueryInput()
    );
    PaymentsReportExportParams::persistFromParsed($params);

    $total = $paymentsReportRepo->countRows($params);
    $rows = $paymentsReportRepo->listPaymentReportRows($params);
    $postAction = ($urlBase === '' ? '' : $urlBase) . '/reports/payments';

    $paymentsReportSortPost = [];
    foreach (['member_name', 'call_sign', 'payment_date', 'paid_through', 'membership_type', 'form_number'] as $field) {
        [$nsb, $nsd] = PaymentsReportRepository::nextSortChoice(
            $params['sort_by'],
            $params['sort_dir'],
            $field
        );
        $paymentsReportSortPost[$field] = ['sort_by' => $nsb, 'sort_dir' => $nsd];
    }

    $inner = View::render('reports_payment_report', [
        'total' => $total,
        'rows' => $rows,
        'start_date' => $params['start_date'],
        'end_date' => $params['end_date'],
        'paid_through_start' => $params['paid_through_start'],
        'paid_through_end' => $params['paid_through_end'],
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
        'paymentsReportSortPost' => $paymentsReportSortPost,
        'paymentsReportPostAction' => $postAction,
        'exportQuery' => '',
        'base' => $urlBase,
    ]);

    $body = $wrapHtml($inner, 'Payment report', [
        'authenticated' => true,
        'activeNav' => 'reports',
        'base' => $urlBase,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->get('/reports/payments/export.csv', $runPaymentsReportExport('csv'))->add($authMiddleware);
$app->get('/reports/payments/export.xlsx', $runPaymentsReportExport('xlsx'))->add($authMiddleware);
$app->get('/reports/payments/export.pdf', $runPaymentsReportExport('pdf'))->add($authMiddleware);

$app->post('/reports/keyholders', function (Request $request, Response $response) use ($urlBase): Response {
    $flat = KeyholdersReportParams::mergedFlatAfterPost($request->getParsedBody() ?? []);
    $params = ReportsRepository::parseKeyholdersQuery($flat);
    KeyholdersReportParams::persistFromParsed($params);
    $loc = ($urlBase === '' ? '' : $urlBase) . '/reports/keyholders';

    return $response->withStatus(303)->withHeader('Location', $loc);
})->add($authMiddleware);

$app->get('/reports/keyholders', function (Request $request, Response $response) use (
    $reportsRepo,
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    unset($request);
    $khParams = ReportsRepository::parseKeyholdersQuery(KeyholdersReportParams::persistedSortInput());
    KeyholdersReportParams::persistFromParsed($khParams);
    $rows = $reportsRepo->listKeyholders($khParams['sort_by'], $khParams['sort_dir']);
    $postAction = ($urlBase === '' ? '' : $urlBase) . '/reports/keyholders';
    $keyholdersSortPost = [];
    foreach (['name', 'call_sign', 'key_number', 'email'] as $field) {
        [$nsb, $nsd] = ReportsRepository::nextKeyholdersSortChoice(
            $khParams['sort_by'],
            $khParams['sort_dir'],
            $field
        );
        $keyholdersSortPost[$field] = ['sort_by' => $nsb, 'sort_dir' => $nsd];
    }

    $body = $wrapHtml(View::render('reports_keyholders', [
        'total' => \count($rows),
        'rows' => $rows,
        'sort_by' => $khParams['sort_by'],
        'sort_dir' => $khParams['sort_dir'],
        'keyholdersSortPost' => $keyholdersSortPost,
        'keyholdersPostAction' => $postAction,
        'exportQuery' => '',
        'base' => $urlBase,
    ]), 'Keyholders', [
        'authenticated' => true,
        'activeNav' => 'reports',
        'base' => $urlBase,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->get('/reports/keyholders/export.csv', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('keyholders-report');
    $kh = KeyholdersReportParams::resolveForExport($request);
    $rows = $reportsRepo->listKeyholders($kh['sort_by'], $kh['sort_dir']);

    return $sendNamedReportExport($response, $stem, 'csv', StaticRosterExports::keyholdersCsvBinary($rows));
})->add($authMiddleware);

$app->get('/reports/keyholders/export.xlsx', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('keyholders-report');
    $kh = KeyholdersReportParams::resolveForExport($request);
    $rows = $reportsRepo->listKeyholders($kh['sort_by'], $kh['sort_dir']);

    return $sendNamedReportExport($response, $stem, 'xlsx', StaticRosterExports::keyholdersXlsxBinary($rows));
})->add($authMiddleware);

$app->get('/reports/keyholders/export.pdf', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('keyholders-report');
    $kh = KeyholdersReportParams::resolveForExport($request);
    $rows = $reportsRepo->listKeyholders($kh['sort_by'], $kh['sort_dir']);

    return $sendNamedReportExport($response, $stem, 'pdf', StaticRosterExports::keyholdersPdfBinary($rows));
})->add($authMiddleware);

$app->get('/reports/roster-by-name', function (Request $request, Response $response) use (
    $reportsRepo,
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    $rows = $reportsRepo->rosterByName();
    $body = $wrapHtml(View::render('reports_roster_name', [
        'total' => \count($rows),
        'rows' => $rows,
        'base' => $urlBase,
    ]), 'Roster by name', [
        'authenticated' => true,
        'activeNav' => 'reports',
        'base' => $urlBase,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->get('/reports/roster-by-name/export.csv', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-name');

    return $sendNamedReportExport($response, $stem, 'csv', StaticRosterExports::rosterByNameCsvBinary($reportsRepo->rosterByName()));
})->add($authMiddleware);

$app->get('/reports/roster-by-name/export.xlsx', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-name');

    return $sendNamedReportExport($response, $stem, 'xlsx', StaticRosterExports::rosterByNameXlsxBinary($reportsRepo->rosterByName()));
})->add($authMiddleware);

$app->get('/reports/roster-by-name/export.pdf', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-name');

    return $sendNamedReportExport($response, $stem, 'pdf', StaticRosterExports::rosterByNamePdfBinary($reportsRepo->rosterByName()));
})->add($authMiddleware);

$app->get('/reports/roster-by-callsign', function (Request $request, Response $response) use (
    $reportsRepo,
    $wrapHtml,
    $htmlResponse,
    $urlBase
): Response {
    $rows = $reportsRepo->rosterByCallsign();
    $body = $wrapHtml(View::render('reports_roster_callsign', [
        'total' => \count($rows),
        'rows' => $rows,
        'base' => $urlBase,
    ]), 'Roster by callsign', [
        'authenticated' => true,
        'activeNav' => 'reports',
        'base' => $urlBase,
    ]);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->get('/reports/roster-by-callsign/export.csv', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-callsign');

    return $sendNamedReportExport($response, $stem, 'csv', StaticRosterExports::rosterByCallsignCsvBinary($reportsRepo->rosterByCallsign()));
})->add($authMiddleware);

$app->get('/reports/roster-by-callsign/export.xlsx', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-callsign');

    return $sendNamedReportExport($response, $stem, 'xlsx', StaticRosterExports::rosterByCallsignXlsxBinary($reportsRepo->rosterByCallsign()));
})->add($authMiddleware);

$app->get('/reports/roster-by-callsign/export.pdf', function (Request $request, Response $response) use ($reportsRepo, $sendNamedReportExport): Response {
    $stem = StaticRosterExports::filenameStem('roster-by-callsign');

    return $sendNamedReportExport($response, $stem, 'pdf', StaticRosterExports::rosterByCallsignPdfBinary($reportsRepo->rosterByCallsign()));
})->add($authMiddleware);

$membersRepo = new MemberRepository($pdo);
$paymentsRepo = new PaymentRepository($pdo);

$mRef = static function () use ($membersRepo): array {
    return [
        'license_classes' => $membersRepo->listLicenseClasses(),
        'membership_types' => $membersRepo->listMembershipTypes(),
    ];
};

$mLocView = static function (string $suffix) use ($urlBase): string {
    return ($urlBase === '' ? '' : $urlBase) . $suffix;
};

$membersExtras = [
    'authenticated' => true,
    'activeNav' => 'members',
    'base' => $urlBase,
];

$app->get('/members/new', function (Request $request, Response $response) use (
    $wrapHtml,
    $htmlResponse,
    $membersExtras,
    $mRef,
    $urlBase
): Response {
    $ctx = array_merge($mRef(), [
        'error' => null,
        'base' => $urlBase,
    ]);
    $body = $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->post('/members/new', function (Request $request, Response $response) use (
    $membersRepo,
    $wrapHtml,
    $htmlResponse,
    $membersExtras,
    $mRef,
    $mLocView,
    $urlBase
): Response {
    /** @var array<string, mixed> $bodyRaw */
    $bodyRaw = $request->getParsedBody();
    $bodyRaw = \is_array($bodyRaw) ? $bodyRaw : [];
    $paidCheck = MemberInputNormalizer::parseOptionalPaidThrough(isset($bodyRaw['paid_through']) ? (string) $bodyRaw['paid_through'] : '');
    $refForms = array_merge($mRef(), ['base' => $urlBase]);
    if (!$paidCheck['ok']) {
        $ctx = array_merge($refForms, ['error' => 'Could not create member.']);

        return $htmlResponse($response->withStatus(400), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    }
    $row = MemberInputNormalizer::memberCreateFromForm($bodyRaw, $paidCheck['date']);
    if (trim($row['last_name']) === '' || trim($row['first_name']) === '') {
        $ctx = array_merge($refForms, ['error' => 'Could not create member.']);

        return $htmlResponse($response->withStatus(400), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    }

    $cs = $row['call_sign'];
    if ($cs !== null && $membersRepo->findIdByNonnullCallSign($cs) !== null) {
        $ctx = array_merge($refForms, ['error' => 'That call sign is already in use.']);

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    }
    if ($cs === null && $membersRepo->existsNameWithoutCallSign($row['last_name'], $row['first_name'], null)) {
        $ctx = array_merge($refForms, ['error' => 'A member with this name already exists without a call sign.']);

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    }

    try {
        $newId = $membersRepo->insertMember($row);
    } catch (DuplicateMemberKeyNumber) {
        $ctx = array_merge($refForms, ['error' => 'That key number is already assigned to another member.']);

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    } catch (\PDOException) {
        $ctx = array_merge($refForms, ['error' => 'Could not create member.']);

        return $htmlResponse($response->withStatus(400), $wrapHtml(View::render('member_new', $ctx), 'New member', $membersExtras));
    }

    return $response->withHeader('Location', $mLocView('/members/' . $newId . '/view'))->withStatus(303);
})->add($authMiddleware);

$app->get('/members/{member_id}/view', function (Request $request, Response $response, array $args) use (
    $membersRepo,
    $wrapHtml,
    $htmlResponse,
    $membersExtras,
    $mRef,
    $urlBase
): Response {
    $id = isset($args['member_id']) ? (int) $args['member_id'] : 0;
    if ($id <= 0 || ($member = $membersRepo->findMemberById($id)) === null) {
        $nf = '<div class="standard-page-scroll"><p class="error">Member not found.</p></div>';

        return $htmlResponse((new SlimResponse(404)), $wrapHtml($nf, 'Not found', $membersExtras));
    }
    $ctx = array_merge($mRef(), [
        'member' => $member,
        'error' => null,
        'base' => $urlBase,
    ]);
    $scripts = View::render('member_detail_scripts');
    $body = $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts]));

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$app->post('/members/{member_id}/edit', function (Request $request, Response $response, array $args) use (
    $membersRepo,
    $wrapHtml,
    $htmlResponse,
    $membersExtras,
    $mRef,
    $mLocView,
    $urlBase
): Response {
    $id = isset($args['member_id']) ? (int) $args['member_id'] : 0;
    if ($id <= 0 || ($member = $membersRepo->findMemberById($id)) === null) {
        $nf = '<div class="standard-page-scroll"><p class="error">Member not found.</p></div>';

        return $htmlResponse((new SlimResponse(404)), $wrapHtml($nf, 'Not found', $membersExtras));
    }
    /** @var array<string, mixed> $bodyRaw */
    $bodyRaw = $request->getParsedBody();
    $bodyRaw = \is_array($bodyRaw) ? $bodyRaw : [];
    $row = MemberInputNormalizer::memberUpdateFromForm($bodyRaw);
    if (trim($row['last_name']) === '' || trim($row['first_name']) === '') {
        $ctx = array_merge($mRef(), ['member' => $member, 'error' => 'Could not save member.', 'base' => $urlBase]);
        $scripts = View::render('member_detail_scripts');

        return $htmlResponse($response->withStatus(400), $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts])));
    }
    $effCall = $row['call_sign'];
    if ($effCall !== null && ($oid = $membersRepo->findIdByNonnullCallSign($effCall)) !== null && $oid !== $id) {
        $ctx = array_merge($mRef(), ['member' => $member, 'error' => 'That call sign is already in use.', 'base' => $urlBase]);
        $scripts = View::render('member_detail_scripts');

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts])));
    }
    if ($effCall === null && $membersRepo->existsNameWithoutCallSign($row['last_name'], $row['first_name'], $id)) {
        $ctx = array_merge($mRef(), ['member' => $member, 'error' => 'Another member with this name already exists without a call sign.', 'base' => $urlBase]);
        $scripts = View::render('member_detail_scripts');

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts])));
    }
    try {
        $membersRepo->updateMember($id, $row);
    } catch (DuplicateMemberKeyNumber) {
        $ctx = array_merge($mRef(), ['member' => $member, 'error' => 'That key number is already assigned to another member.', 'base' => $urlBase]);
        $scripts = View::render('member_detail_scripts');

        return $htmlResponse($response->withStatus(409), $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts])));
    } catch (\PDOException) {
        $ctx = array_merge($mRef(), ['member' => $member, 'error' => 'Could not save member.', 'base' => $urlBase]);
        $scripts = View::render('member_detail_scripts');

        return $htmlResponse($response->withStatus(400), $wrapHtml(View::render('member_detail', $ctx), 'Member', array_merge($membersExtras, ['extraScriptsHtml' => $scripts])));
    }

    return $response->withHeader('Location', $mLocView('/members/' . $id . '/view'))->withStatus(303);
})->add($authMiddleware);

$app->post('/members/{member_id}/delete', function (Request $request, Response $response, array $args) use ($membersRepo, $mLocView): Response {
    $id = isset($args['member_id']) ? (int) $args['member_id'] : 0;
    if ($id > 0) {
        $membersRepo->deleteMemberById($id);
    }

    return $response->withHeader('Location', $mLocView('/'))->withStatus(303);
})->add($authMiddleware);

$app->post('/members/{member_id}/payments/new', function (Request $request, Response $response, array $args) use (
    $membersRepo,
    $paymentsRepo,
    $mLocView
): Response {
    $memberId = isset($args['member_id']) ? (int) $args['member_id'] : 0;
    if ($memberId <= 0 || $membersRepo->findMemberById($memberId) === null) {
        return $response->withHeader('Location', $mLocView('/'))->withStatus(303);
    }
    /** @var array<string, mixed> $bodyRaw */
    $bodyRaw = $request->getParsedBody();
    $bodyRaw = \is_array($bodyRaw) ? $bodyRaw : [];
    $parsed = MemberInputNormalizer::paymentFromForm($bodyRaw);
    if (!$parsed['ok']) {
        $_SESSION['dvra_flash_payment_error'] = $parsed['error'];

        return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
    }
    try {
        $paymentsRepo->insertPayment($memberId, $parsed['data']);
    } catch (\Throwable) {
        $_SESSION['dvra_flash_payment_error'] = 'Could not save payment.';

        return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
    }

    return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
})->add($authMiddleware);

$app->post('/payments/{payment_id}/edit', function (Request $request, Response $response, array $args) use (
    $paymentsRepo,
    $mLocView
): Response {
    $paymentId = isset($args['payment_id']) ? (int) $args['payment_id'] : 0;
    /** @var array<string, mixed> $bodyRaw */
    $bodyRaw = $request->getParsedBody();
    $bodyRaw = \is_array($bodyRaw) ? $bodyRaw : [];
    $parsed = MemberInputNormalizer::paymentFromForm($bodyRaw);
    $meta = $paymentId > 0 ? $paymentsRepo->findPaymentMeta($paymentId) : null;
    if ($meta === null) {
        return $response->withHeader('Location', $mLocView('/'))->withStatus(303);
    }
    $memberId = $meta['member_id'];
    if (!$parsed['ok']) {
        $_SESSION['dvra_flash_payment_error'] = $parsed['error'];

        return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
    }
    try {
        $mid = $paymentsRepo->updatePayment($paymentId, $parsed['data']);
        if ($mid === null) {
            return $response->withHeader('Location', $mLocView('/'))->withStatus(303);
        }
    } catch (\Throwable) {
        $_SESSION['dvra_flash_payment_error'] = 'Could not save payment.';

        return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
    }

    return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
})->add($authMiddleware);

$app->post('/payments/{payment_id}/delete', function (Request $request, Response $response, array $args) use (
    $paymentsRepo,
    $mLocView
): Response {
    $paymentId = isset($args['payment_id']) ? (int) $args['payment_id'] : 0;
    $meta = $paymentId > 0 ? $paymentsRepo->findPaymentMeta($paymentId) : null;
    if ($meta === null) {
        return $response->withHeader('Location', $mLocView('/'))->withStatus(303);
    }
    $memberId = $meta['member_id'];
    try {
        $paymentsRepo->deletePayment($paymentId);
    } catch (\Throwable) {
        $_SESSION['dvra_flash_payment_error'] = 'Could not delete payment.';
    }

    return $response->withHeader('Location', $mLocView('/members/' . $memberId . '/payments'))->withStatus(303);
})->add($authMiddleware);

$app->get('/members/{member_id}/payments', function (Request $request, Response $response, array $args) use (
    $membersRepo,
    $wrapHtml,
    $htmlResponse,
    $membersExtras,
    $urlBase
): Response {
    $id = isset($args['member_id']) ? (int) $args['member_id'] : 0;
    if ($id <= 0 || ($member = $membersRepo->findMemberById($id)) === null) {
        $nf = '<div class="standard-page-scroll"><p class="error">Member not found.</p></div>';

        return $htmlResponse((new SlimResponse(404)), $wrapHtml($nf, 'Not found', $membersExtras));
    }
    $flashError = null;
    if (!empty($_SESSION['dvra_flash_payment_error'])) {
        $flashError = (string) $_SESSION['dvra_flash_payment_error'];
        unset($_SESSION['dvra_flash_payment_error']);
    }
    $payments = $membersRepo->listPaymentsForMember($id);
    // Strip display-only field for edit rows (template uses membership_type_id).
    foreach ($payments as $k => $p) {
        unset($payments[$k]['membership_type_display']);
    }
    $ctx = [
        'member' => $member,
        'payments' => $payments,
        'membership_types' => $membersRepo->listMembershipTypes(),
        'flash_error' => $flashError,
        'base' => $urlBase,
    ];
    $scripts = View::render('member_payments_scripts');
    $body = $wrapHtml(View::render('member_payments', $ctx), 'Payments', array_merge($membersExtras, ['extraScriptsHtml' => $scripts]));

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$displayDetails = getenv('DVRA_DISPLAY_PHP_ERRORS') === '1';
$app->addErrorMiddleware(!$displayDetails, !$displayDetails, !$displayDetails);

$app->run();
