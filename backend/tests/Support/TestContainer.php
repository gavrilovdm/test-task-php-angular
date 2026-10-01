<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use App\Service\Import\ImageDownloader;
use App\Service\Import\ImportService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function DI\autowire;

/** Container with test doubles: in-memory queue and rate limiter storage, fake image server, temp dirs. */
final class TestContainer
{
    /** 1×1 transparent PNG. */
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): ContainerInterface
    {
        $tmp = sys_get_temp_dir().'/products-import-tests';

        return Kernel::createContainer([
            LoggerInterface::class => new NullLogger(),
            'messenger.transport.async' => static fn (): InMemoryTransport => new InMemoryTransport(),
            'rate_limiter.storage' => static fn (): InMemoryStorage => new InMemoryStorage(),
            'http.images' => static fn (): Client => self::fakeImageClient(),
            ImageDownloader::class => static fn (ContainerInterface $c): ImageDownloader => new ImageDownloader($c->get('http.images'), $tmp.'/images', '/uploads/products'),
            ImportService::class => autowire()->constructorParameter('importsDir', $tmp.'/imports'),
            ...$overrides,
        ]);
    }

    /** Responds with a PNG for every URL, except the broken sample link (5581481), "forbidden" and "notimage" URLs. */
    public static function fakeImageClient(): Client
    {
        $handler = static function (RequestInterface $request) {
            $url = (string) $request->getUri();

            return Create::promiseFor(match (true) {
                str_contains($url, '5581481') || str_contains($url, 'forbidden') => new Response(403, [], 'Forbidden'),
                str_contains($url, 'notimage') => new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
                default => new Response(200, ['Content-Type' => 'binary/octet-stream'], (string) base64_decode(self::PNG, true)),
            });
        };

        return new Client(['handler' => HandlerStack::create($handler)]);
    }
}
