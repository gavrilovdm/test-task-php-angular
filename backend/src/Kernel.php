<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\JsonErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/** Builds the DI container and the Slim application (routes + middleware). */
final class Kernel
{
    /**
     * @param array<string, mixed> $overrides container definitions replacing the defaults (used by tests)
     */
    public static function createContainer(array $overrides = []): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions(__DIR__.'/../config/container.php');
        if ([] !== $overrides) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }

    /**
     * @return App<ContainerInterface>
     */
    public static function createApp(?ContainerInterface $container = null): App
    {
        $container ??= self::createContainer();
        /** @var App<ContainerInterface> $app */
        $app = AppFactory::createFromContainer($container);

        self::registerRoutes($app);

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(!$container->get(Settings::class)->isProduction(), false, false);
        $errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));
        // CORS is the outermost layer so that error responses also carry CORS headers.
        $app->add(CorsMiddleware::class);

        return $app;
    }

    /**
     * @param App<ContainerInterface> $app
     */
    private static function registerRoutes(App $app): void
    {
        $app->group('/api', function (RouteCollectorProxy $api): void {
            $api->get('/health', HealthController::class);
            $api->get('/docs', [DocsController::class, 'ui']);
            $api->get('/docs/openapi.yaml', [DocsController::class, 'spec']);
            $api->post('/auth/login', [AuthController::class, 'login']);

            $api->group('', function (RouteCollectorProxy $secured): void {
                $secured->get('/auth/me', [AuthController::class, 'me']);

                $secured->get('/products', [ProductController::class, 'index']);
                $secured->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);

                $secured->post('/imports', [ImportController::class, 'create'])->add(RateLimitMiddleware::class);
                $secured->get('/imports', [ImportController::class, 'index']);
                $secured->get('/imports/{id}', [ImportController::class, 'show']);
            })->add(JwtAuthMiddleware::class);
        });
    }
}
