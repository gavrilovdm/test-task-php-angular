<?php

declare(strict_types=1);

use App\Controller\DocsController;
use App\Http\StderrLogger;
use App\Messenger\ImportProductsHandler;
use App\Messenger\ImportProductsMessage;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Service\Auth\AuthService;
use App\Service\Import\ImageDownloader;
use App\Service\Import\ImportService;
use App\Service\Import\ImportUploadValidator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

use function DI\autowire;
use function DI\get;

return [
    'settings' => require __DIR__.'/settings.php',

    LoggerInterface::class => static fn (): LoggerInterface => new StderrLogger(),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    // ---------- Doctrine ----------
    EntityManagerInterface::class => static function (ContainerInterface $c): EntityManagerInterface {
        $s = $c->get('settings');
        $isDev = 'prod' !== $s['env'];
        $cache = $isDev ? new ArrayAdapter() : new PhpFilesAdapter('doctrine', 0, $s['cache_dir']);

        $config = ORMSetup::createAttributeMetadataConfig([$s['root'].'/src/Entity'], $isDev, null, $cache);
        $config->enableNativeLazyObjects(true);

        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql']))->parse($s['database_url']);

        return new EntityManager(DriverManager::getConnection($params, $config), $config);
    },
    EntityManager::class => get(EntityManagerInterface::class),
    Connection::class => static fn (EntityManagerInterface $em): Connection => $em->getConnection(),

    // ---------- Messenger (RabbitMQ via AMQP; "in-memory://" for tests) ----------
    'messenger.transport.async' => static function (ContainerInterface $c): TransportInterface {
        $dsn = $c->get('settings')['messenger_dsn'];
        if (str_starts_with($dsn, 'in-memory://')) {
            return new InMemoryTransport();
        }

        return (new AmqpTransportFactory())->createTransport($dsn, [], new PhpSerializer());
    },
    MessageBusInterface::class => static fn (ContainerInterface $c): MessageBusInterface => new MessageBus([
        new SendMessageMiddleware(new SendersLocator([ImportProductsMessage::class => ['messenger.transport.async']], $c)),
        new HandleMessageMiddleware(new HandlersLocator([
            ImportProductsMessage::class => [static fn (ImportProductsMessage $m) => $c->get(ImportProductsHandler::class)($m)],
        ])),
    ]),

    // ---------- Services ----------
    AuthService::class => static fn (ContainerInterface $c): AuthService => new AuthService(
        $c->get('settings')['admin']['email'],
        $c->get('settings')['admin']['password'],
        $c->get('settings')['jwt']['secret'],
        $c->get('settings')['jwt']['ttl'],
    ),
    ImportUploadValidator::class => static fn (ContainerInterface $c): ImportUploadValidator => new ImportUploadValidator($c->get('settings')['import']['max_file_size']),
    ImportService::class => autowire()->constructorParameter('importsDir', DI\factory(static fn (ContainerInterface $c): string => $c->get('settings')['import']['dir'])),
    'http.images' => static fn (ContainerInterface $c): Client => new Client([
        'timeout' => $c->get('settings')['images']['timeout'],
        'connect_timeout' => 5,
        'headers' => ['User-Agent' => 'ProductsImporter/1.0'],
    ]),
    ImageDownloader::class => static fn (ContainerInterface $c): ImageDownloader => new ImageDownloader(
        $c->get('http.images'),
        $c->get('settings')['images']['dir'],
        $c->get('settings')['images']['public_prefix'],
    ),

    // ---------- HTTP ----------
    'rate_limiter.storage' => static fn (ContainerInterface $c): CacheStorage => new CacheStorage(new FilesystemAdapter('rate_limiter', 0, $c->get('settings')['cache_dir'])),
    'rate_limiter.import' => static fn (ContainerInterface $c): RateLimiterFactory => new RateLimiterFactory([
        'id' => 'import',
        'policy' => 'sliding_window',
        'limit' => $c->get('settings')['import']['rate_limit'],
        'interval' => $c->get('settings')['import']['rate_interval'],
    ], $c->get('rate_limiter.storage')),
    RateLimitMiddleware::class => static fn (ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware(
        $c->get('rate_limiter.import'),
        $c->get(ResponseFactoryInterface::class),
        $c->get(App\Http\JsonErrorHandler::class),
    ),
    CorsMiddleware::class => static fn (ContainerInterface $c): CorsMiddleware => new CorsMiddleware(
        $c->get('settings')['cors_allow_origin'],
        $c->get(ResponseFactoryInterface::class),
    ),
    DocsController::class => static fn (ContainerInterface $c): DocsController => new DocsController($c->get('settings')['openapi_spec']),
];
