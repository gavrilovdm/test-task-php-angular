<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Controller\DocsController;
use App\Http\JsonErrorHandler;
use App\Logging\StderrLogger;
use App\Messenger\ImportProductsHandler;
use App\Messenger\ImportProductsMessage;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Persistence\DatabaseHealthCheck;
use App\Persistence\DoctrineDatabaseHealthCheck;
use App\Persistence\DoctrineUnitOfWork;
use App\Persistence\UnitOfWork;
use App\Repository\Doctrine\DoctrineImportJobRepository;
use App\Repository\Doctrine\DoctrineProductRepository;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Auth\AuthService;
use App\Service\Import\Contract\ImageFetcher;
use App\Service\Import\Contract\ImageStorage;
use App\Service\Import\Contract\ImportFileStorage;
use App\Service\Import\Contract\ProductSourceReader;
use App\Service\Import\Image\HttpImageFetcher;
use App\Service\Import\Image\LocalImageStorage;
use App\Service\Import\ImportUploadValidator;
use App\Service\Import\Row\ColumnMap;
use App\Service\Import\Source\XlsxProductReader;
use App\Service\Import\Storage\LocalImportFileStorage;
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
use Symfony\Component\RateLimiter\Storage\StorageInterface;

use function DI\autowire;
use function DI\get;

return [
    Settings::class => static fn (): Settings => Settings::fromEnvironment(dirname(__DIR__)),

    LoggerInterface::class => static fn (): LoggerInterface => new StderrLogger(),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    // ---------- Persistence (Doctrine) ----------
    EntityManagerInterface::class => static function (Settings $s): EntityManagerInterface {
        $cache = $s->isProduction() ? new PhpFilesAdapter('doctrine', 0, $s->cacheDir) : new ArrayAdapter();
        $config = ORMSetup::createAttributeMetadataConfig([$s->rootDir.'/src/Entity'], !$s->isProduction(), null, $cache);
        $config->enableNativeLazyObjects(true);

        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql']))->parse($s->databaseUrl);

        return new EntityManager(DriverManager::getConnection($params, $config), $config);
    },
    EntityManager::class => get(EntityManagerInterface::class),
    Connection::class => static fn (EntityManagerInterface $em): Connection => $em->getConnection(),
    UnitOfWork::class => autowire(DoctrineUnitOfWork::class),
    DatabaseHealthCheck::class => autowire(DoctrineDatabaseHealthCheck::class),
    ProductRepository::class => autowire(DoctrineProductRepository::class),
    ImportJobRepository::class => autowire(DoctrineImportJobRepository::class),

    // ---------- Messenger (RabbitMQ via AMQP; "in-memory://" for tests) ----------
    'messenger.transport.async' => static fn (Settings $s): TransportInterface => str_starts_with($s->messengerDsn, 'in-memory://')
        ? new InMemoryTransport()
        : (new AmqpTransportFactory())->createTransport($s->messengerDsn, [], new PhpSerializer()),
    MessageBusInterface::class => static fn (ContainerInterface $c): MessageBusInterface => new MessageBus([
        new SendMessageMiddleware(new SendersLocator([ImportProductsMessage::class => ['messenger.transport.async']], $c)),
        new HandleMessageMiddleware(new HandlersLocator([
            ImportProductsMessage::class => [static fn (ImportProductsMessage $m) => $c->get(ImportProductsHandler::class)($m)],
        ])),
    ]),

    // ---------- Import ----------
    ColumnMap::class => static fn (): ColumnMap => ColumnMap::default(),
    ProductSourceReader::class => autowire(XlsxProductReader::class),
    ImportFileStorage::class => static fn (Settings $s): ImportFileStorage => new LocalImportFileStorage($s->importsDir),
    ImportUploadValidator::class => static fn (Settings $s): ImportUploadValidator => new ImportUploadValidator($s->importMaxFileSize),
    ImageFetcher::class => static fn (Settings $s): ImageFetcher => new HttpImageFetcher(new Client([
        'timeout' => $s->imageDownloadTimeout,
        'connect_timeout' => 5,
        'headers' => ['User-Agent' => 'ProductsImporter/1.0'],
    ])),
    ImageStorage::class => static fn (Settings $s): ImageStorage => new LocalImageStorage($s->imagesDir, $s->imagesPublicPrefix),

    // ---------- Auth ----------
    AuthService::class => static fn (Settings $s): AuthService => new AuthService($s->adminEmail, $s->adminPasswordHash, $s->jwtSecret, $s->jwtTtl),

    // ---------- HTTP ----------
    StorageInterface::class => static fn (Settings $s): StorageInterface => new CacheStorage(new FilesystemAdapter('rate_limiter', 0, $s->cacheDir)),
    RateLimitMiddleware::class => static fn (Settings $s, ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware(
        new RateLimiterFactory([
            'id' => 'import',
            'policy' => 'sliding_window',
            'limit' => $s->importRateLimit,
            'interval' => $s->importRateInterval,
        ], $c->get(StorageInterface::class)),
        $c->get(ResponseFactoryInterface::class),
        $c->get(JsonErrorHandler::class),
    ),
    CorsMiddleware::class => static fn (Settings $s, ResponseFactoryInterface $f): CorsMiddleware => new CorsMiddleware($s->corsAllowOrigin, $f),
    DocsController::class => static fn (Settings $s): DocsController => new DocsController($s->openApiSpecPath),
];
