<?php

declare(strict_types=1);

use App\Application\Middleware\CorsMiddleware;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logger;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

require __DIR__ . '/../vendor/autoload.php';

// --- Env ---
$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$appEnv = $_ENV['APP_ENV'] ?? 'production';

// --- Container ---
$container = new Container();

$container->set(Logger::class, fn () => new Logger(__DIR__ . '/../logs/app.log'));
$container->set(PDO::class, fn () => Connection::get());
$container->set(
    \App\Infrastructure\Upload\ImageUploadService::class,
    fn () => new \App\Infrastructure\Upload\ImageUploadService(__DIR__ . '/../storage/uploads')
);
$container->set(
    \App\Domain\Mail\MailerInterface::class,
    function (Container $c) {
        $appUrl = $_ENV['APP_URL'] ?? 'https://tacchettoimmobiliare.it';
        $brevoKey = $_ENV['BREVO_API_KEY'] ?? '';
        $brevo = $brevoKey !== ''
            ? new \App\Infrastructure\Mail\BrevoService(
                $brevoKey,
                $_ENV['MAIL_FROM'] ?? 'info@rtimmobiliare.it',
                $_ENV['MAIL_FROM_NAME'] ?? 'Roberto Tacchetto — RT CASA LIVE'
            )
            : null;

        return new \App\Infrastructure\Mail\MailService(
            $c->get(PDO::class),
            new \App\Infrastructure\Mail\EmailTemplates($appUrl),
            $c->get(Logger::class),
            $brevo
        );
    }
);
$container->set(CorsMiddleware::class, fn () => new CorsMiddleware(
    $_ENV['CORS_ALLOWED_ORIGINS']
        ?? 'https://www.rtimmobiliare.it,https://rtimmobiliare.it,http://localhost:5173,http://localhost:5174'
));

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('/api');

// --- Middleware ---
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add($container->get(CorsMiddleware::class));
$app->add(new \App\Application\Middleware\SecurityHeadersMiddleware());

// --- Error handler JSON uniforme: {"error":{"code","message"}} ---
$errorMiddleware = $app->addErrorMiddleware($appEnv !== 'production', false, false);
$errorMiddleware->setDefaultErrorHandler(
    function (
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) use ($container, $appEnv): ResponseInterface {
        $logger = $container->get(Logger::class);

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $code = 'not_found';
            $message = 'Risorsa non trovata.';
        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $code = 'method_not_allowed';
            $message = 'Metodo non consentito.';
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $code = 'http_error';
            $message = $exception->getMessage();
        } else {
            $status = 500;
            $code = 'internal_error';
            $message = $appEnv !== 'production' && $displayErrorDetails
                ? $exception->getMessage()
                : 'Errore interno. Riprova più tardi.';
            $logger->error('Eccezione non gestita', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
                'uri' => (string) $request->getUri(),
            ]);
        }

        $response = new SlimResponse($status);
        $response->getBody()->write(json_encode(
            ['error' => ['code' => $code, 'message' => $message]],
            JSON_UNESCAPED_UNICODE
        ));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
);

// --- Route ---
(require __DIR__ . '/../config/routes.php')($app);

$app->run();
