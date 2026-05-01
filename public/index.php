<?php

declare(strict_types=1);

use DvraMembership\Support\AdminSeed;
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

$authMiddleware = static function (Request $request, RequestHandler $handler): Response {
    if (!isset($_SESSION['admin_id'])) {
        return (new SlimResponse(302))->withHeader('Location', '/login');
    }
    return $handler->handle($request);
};

$app->get('/health', function (Request $request, Response $response): Response {
    $response->getBody()->write('OK');
    return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
});

$app->get('/login', function (Request $request, Response $response) use ($wrapHtml, $htmlResponse): Response {
    if (isset($_SESSION['admin_id'])) {
        return $response->withHeader('Location', '/')->withStatus(302);
    }
    $body = $wrapHtml(View::render('login', ['error' => null, 'base' => '']), 'Login', []);
    return $htmlResponse($response, $body);
});

$app->post('/login', function (Request $request, Response $response) use ($pdo, $wrapHtml, $htmlResponse, $authenticated): Response {
    if ($authenticated()) {
        return $response->withHeader('Location', '/')->withStatus(302);
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
        return $response->withHeader('Location', '/')->withStatus(303);
    }
    $body = $wrapHtml(
        View::render('login', ['error' => 'Invalid username or password.', 'base' => '']),
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
    return $response->withHeader('Location', '/login')->withStatus(303);
});

$app->get('/', function (Request $request, Response $response) use ($pdo, $wrapHtml, $htmlResponse): Response {
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM members');
    $total = (int) $stmt->fetch()['c'];
    $list = $pdo->query(
        'SELECT id, last_name, first_name, call_sign, paid_through FROM members ORDER BY last_name ASC, first_name ASC LIMIT 100'
    )->fetchAll();
    $inner = View::render('home', ['rows' => $list, 'total' => $total, 'base' => '']);
    $body = $wrapHtml($inner, 'Members', ['authenticated' => true]);
    return $htmlResponse($response, $body);
})->add($authMiddleware);

$displayDetails = getenv('DVRA_DISPLAY_PHP_ERRORS') === '1';
$app->addErrorMiddleware(!$displayDetails, !$displayDetails, !$displayDetails);

$app->run();
