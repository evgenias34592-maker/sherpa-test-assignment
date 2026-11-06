<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\DocsController;
use App\Settings;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class DocsControllerTest extends TestCase
{
    public function testDocsPageUsesBundledSwagger(): void
    {
        $controller = new DocsController($this->settings());
        $response = $controller->ui(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/docs'),
            new Response(),
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('unpkg.com', $html);
        self::assertStringNotContainsString('swagger.io', $html);
        self::assertStringContainsString('href="/api/docs/swagger-ui.css"', $html);
        self::assertStringContainsString('src="/api/docs/swagger-ui-bundle.js"', $html);
        self::assertStringContainsString("url: '/api/openapi.yaml'", $html);
    }

    public function testServesSwaggerAssetsFromTheProject(): void
    {
        $container = new Container();
        $container->set(Settings::class, $this->settings());
        $app = \DI\Bridge\Slim\Bridge::create($container);
        $app->get('/api/docs/{asset}', [DocsController::class, 'asset']);

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/docs/swagger-ui-bundle.js'),
        );
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/javascript; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('SwaggerUIBundle', $body);
        self::assertStringNotContainsString('unpkg.com/swagger-ui-dist', $body);

        $missing = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/docs/not-a-file.js'),
        );
        self::assertSame(404, $missing->getStatusCode());
    }

    private function settings(): Settings
    {
        return new Settings(
            environment: 'test',
            debug: false,
            databaseUrl: 'postgresql://products:products@127.0.0.1/products',
            jwtSecret: 'test-secret-test-secret-test-secret',
            jwtTtl: 3600,
            storagePath: sys_get_temp_dir(),
            importMaxBytes: 10,
            importRateLimit: 1,
            importRateWindow: 60,
            loginRateLimit: 1,
            imageMaxBytes: 10,
            imageTimeout: 1,
            rabbitDsn: 'amqp://products:products@127.0.0.1/%2f',
            messengerTransport: 'in-memory',
            adminEmail: 'admin@example.com',
            adminPassword: 'admin12345',
            adminName: 'Administrator',
            corsOrigin: 'http://localhost:4200',
            projectDir: dirname(__DIR__, 2),
        );
    }
}
