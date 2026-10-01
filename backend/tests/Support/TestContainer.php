<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use App\Service\Import\Contract\ImageFetcher;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Container with test doubles: fake image server, in-memory rate limiter storage, silent logger.
 * The queue (in-memory://) and temp directories are configured through phpunit.xml env variables.
 */
final class TestContainer
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): ContainerInterface
    {
        return Kernel::createContainer([
            LoggerInterface::class => new NullLogger(),
            StorageInterface::class => static fn (): InMemoryStorage => new InMemoryStorage(),
            ImageFetcher::class => static fn (): FakeImageFetcher => new FakeImageFetcher(),
            ...$overrides,
        ]);
    }
}
