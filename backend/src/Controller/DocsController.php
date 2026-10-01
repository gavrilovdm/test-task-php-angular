<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Serves the OpenAPI spec and a Swagger UI page. */
final class DocsController
{
    public function __construct(private readonly string $specPath)
    {
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(<<<'HTML'
            <!doctype html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <title>Products API — Swagger UI</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
              <script>
                window.ui = SwaggerUIBundle({ url: '/api/docs/openapi.yaml', dom_id: '#swagger-ui', persistAuthorization: true });
              </script>
            </body>
            </html>
            HTML);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write((string) file_get_contents($this->specPath));

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
