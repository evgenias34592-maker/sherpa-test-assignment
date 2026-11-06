<?php

declare(strict_types=1);

namespace App\Controller;

use App\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class DocsController
{
    /** swagger-ui-dist 5.31.0, отдаётся с этого сервера. */
    private const ASSETS = [
        'swagger-ui.css' => 'text/css; charset=utf-8',
        'swagger-ui-bundle.js' => 'text/javascript; charset=utf-8',
    ];

    public function __construct(private readonly Settings $settings)
    {
    }

    public function openapi(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        unset($request);
        $path = $this->settings->projectDir . '/openapi.yaml';
        $yaml = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($yaml)) {
            $response->getBody()->write('openapi spec is missing');

            return $response->withStatus(404);
        }
        $response->getBody()->write($yaml);

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        unset($request);
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Products API</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/api/docs/swagger-ui.css">
</head>
<body>
<div id="swagger"></div>
<script src="/api/docs/swagger-ui-bundle.js"></script>
<script>
window.ui = SwaggerUIBundle({ url: '/api/openapi.yaml', dom_id: '#swagger', validatorUrl: null });
</script>
</body>
</html>
HTML;
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function asset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $name = RouteContext::fromRequest($request)->getRoute()?->getArgument('asset');
        if (!is_string($name) || !isset(self::ASSETS[$name])) {
            $response->getBody()->write('not found');

            return $response->withStatus(404);
        }
        $path = $this->settings->projectDir . '/resources/swagger-ui/' . $name;
        $body = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($body)) {
            $response->getBody()->write('swagger asset is missing');

            return $response->withStatus(404);
        }
        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', self::ASSETS[$name])
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
