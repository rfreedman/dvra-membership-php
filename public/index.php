<?php

declare(strict_types=1);

use DvraMembership\Repository\DuplicateMemberKeyNumber;
use DvraMembership\Repository\MemberListRepository;
use DvraMembership\Repository\MemberRepository;
use DvraMembership\Support\AdminSeed;
use DvraMembership\Support\MemberInputNormalizer;
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

$app->get('/', function (Request $request, Response $response) use ($pdo, $wrapHtml, $htmlResponse, $urlBase, $tabulatorCss): Response {
    $params = MemberListRepository::parseListQuery($request->getQueryParams());
    $repo = new MemberListRepository($pdo);
    $filter = [
        'search' => $params['search'],
        'membership_type_id' => $params['membership_type_id'],
        'arrl' => $params['arrl'],
        'has_key' => $params['has_key'],
        'current_only' => $params['current_only'],
    ];
    $total = $repo->countMembers($filter);
    $tabulatorRows = $repo->listRowsForTabulator(array_merge($filter, [
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
    ]), $urlBase);
    $membersJson = MemberListRepository::tabulatorJsonFromRows($tabulatorRows);

    $qs = $request->getUri()->getQuery();
    $exportQuery = $qs !== '' ? '?' . $qs : '';
    $clearFiltersHref = ($urlBase === '' ? '' : $urlBase) . '/?' . http_build_query([
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
    ]);

    $inner = View::render('home', [
        'total' => $total,
        'search' => $params['search'],
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
        'membership_type_id' => $params['membership_type_id'],
        'arrl' => $params['arrl'],
        'has_key' => $params['has_key'],
        'current_only' => $params['current_only'],
        'membership_types' => $repo->listMembershipTypes(),
        'clearFiltersHref' => $clearFiltersHref,
        'exportQuery' => $exportQuery,
        'base' => $urlBase,
    ]);
    $scripts = View::render('home_tabulator_scripts', [
        'membersJson' => $membersJson,
        'sort_by' => $params['sort_by'],
        'sort_dir' => $params['sort_dir'],
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

$membersRepo = new MemberRepository($pdo);

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
    $payments = $membersRepo->listPaymentsForMember($id);
    $ctx = [
        'member' => $member,
        'payments' => $payments,
        'base' => $urlBase,
    ];
    $body = $wrapHtml(View::render('member_payments_readonly', $ctx), 'Payments', $membersExtras);

    return $htmlResponse($response, $body);
})->add($authMiddleware);

$displayDetails = getenv('DVRA_DISPLAY_PHP_ERRORS') === '1';
$app->addErrorMiddleware(!$displayDetails, !$displayDetails, !$displayDetails);

$app->run();
