<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\ApiException;
use App\Http\AuthMiddleware;
use App\Http\CorsMiddleware;
use App\Http\JsonResponse;
use App\Http\RateLimitMiddleware;
use DI\ContainerBuilder;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollectorProxy;

final class Bootstrap
{
    public static function container(): ContainerInterface
    {
        self::loadEnv();
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions(dirname(__DIR__) . '/config/container.php');

        return $builder->build();
    }

    /** @return App<ContainerInterface> */
    public static function app(ContainerInterface $container): App
    {
        $app = \DI\Bridge\Slim\Bridge::create($container);
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $settings = $container->get(Settings::class);
        $logger = $container->get(LoggerInterface::class);
        $json = $container->get(JsonResponse::class);
        $error = $app->addErrorMiddleware($settings->debug, true, true);
        $error->setDefaultErrorHandler(static function (
            ServerRequestInterface $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
        ) use ($json, $logger, $settings): ResponseInterface {
            unset($request, $displayErrorDetails, $logErrors, $logErrorDetails);
            $status = 500;
            $message = 'Внутренняя ошибка сервера';
            if ($exception instanceof ApiException) {
                $status = $exception->status();
                $message = $exception->getMessage();
            } elseif ($exception instanceof HttpNotFoundException) {
                $status = 404;
                $message = 'Маршрут не найден';
            } elseif ($exception instanceof HttpException) {
                $status = $exception->getCode() >= 400 ? $exception->getCode() : 500;
                $message = $settings->debug ? $exception->getMessage() : 'Ошибка запроса';
            }
            if ($status >= 500 && $logger instanceof Logger) {
                $logger->error($exception->getMessage(), ['exception' => $exception]);
            } elseif ($status >= 500) {
                $logger->error($exception->getMessage());
            }
            $response = $json->write(new Response(), ['error' => $message], $status);
            if ($status === 429) {
                $response = $response->withHeader('Retry-After', '60');
            }

            return $response;
        });

        $app->add(CorsMiddleware::class);
        self::routes($app);

        return $app;
    }

    public static function run(): void
    {
        $container = self::container();
        $app = self::app($container);
        $app->run(ServerRequestCreatorFactory::create()->createServerRequestFromGlobals());
    }

    /** @param App<ContainerInterface> $app */
    private static function routes(App $app): void
    {
        $app->get('/api/health', [HealthController::class, 'show']);
        $app->post('/api/auth/login', [AuthController::class, 'login']);
        $app->get('/api/openapi.yaml', [DocsController::class, 'openapi']);
        $app->get('/api/docs', [DocsController::class, 'ui']);
        $app->get('/api/docs/{asset}', [DocsController::class, 'asset']);
        $app->options('/{routes:.+}', static function ($request, ResponseInterface $response): ResponseInterface {
            unset($request);

            return $response;
        });

        $app->group('/api', function (RouteCollectorProxy $group): void {
            $group->get('/products/export', [ProductController::class, 'export']);
            $group->get('/products', [ProductController::class, 'index']);
            $group->get('/products/{id}', [ProductController::class, 'show']);
            $group->post('/imports', [ImportController::class, 'create'])
                ->add(RateLimitMiddleware::class)
                ->add(AuthMiddleware::class);
            $group->get('/imports/{id}', [ImportController::class, 'show']);
        })->add(AuthMiddleware::class);
    }

    private static function loadEnv(): void
    {
        $backend = dirname(__DIR__);
        $root = dirname($backend);
        if (class_exists(\Dotenv\Dotenv::class)) {
            if (is_file($root . '/.env')) {
                \Dotenv\Dotenv::createImmutable($root)->safeLoad();
            }
            if (is_file($backend . '/.env')) {
                \Dotenv\Dotenv::createImmutable($backend)->safeLoad();
            }
        }
        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');
    }
}
