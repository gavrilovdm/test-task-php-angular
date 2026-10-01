<?php

declare(strict_types=1);

namespace App\Config;

/** Typed application configuration built from environment variables. */
final readonly class Settings
{
    public function __construct(
        public string $env,
        public string $rootDir,
        public string $databaseUrl,
        public string $messengerDsn,
        public string $jwtSecret,
        public int $jwtTtl,
        public string $adminEmail,
        public string $adminPasswordHash,
        public int $importMaxFileSize,
        public int $importRateLimit,
        public string $importRateInterval,
        public string $importsDir,
        public string $imagesDir,
        public string $imagesPublicPrefix,
        public float $imageDownloadTimeout,
        public string $corsAllowOrigin,
        public string $cacheDir,
        public string $openApiSpecPath,
    ) {
    }

    public static function fromEnvironment(string $rootDir): self
    {
        return new self(
            env: self::env('APP_ENV', 'prod'),
            rootDir: $rootDir,
            databaseUrl: self::env('DATABASE_URL', 'postgresql://app:app_secret@db:5432/products'),
            messengerDsn: self::env('MESSENGER_TRANSPORT_DSN', 'amqp://app:app_secret@rabbitmq:5672/%2f/messages'),
            jwtSecret: self::env('JWT_SECRET', 'insecure-development-secret-change-me-please'),
            jwtTtl: (int) self::env('JWT_TTL', '3600'),
            adminEmail: self::env('ADMIN_EMAIL', 'admin@example.com'),
            adminPasswordHash: self::env('ADMIN_PASSWORD_HASH'),
            importMaxFileSize: (int) self::env('IMPORT_MAX_FILE_SIZE', (string) (10 * 1024 * 1024)),
            importRateLimit: (int) self::env('IMPORT_RATE_LIMIT', '5'),
            importRateInterval: self::env('IMPORT_RATE_INTERVAL', '1 minute'),
            // Directories are overridable for tests; in docker they are mounted volumes.
            importsDir: self::env('IMPORTS_DIR', $rootDir.'/var/imports'),
            imagesDir: self::env('IMAGES_DIR', $rootDir.'/public/uploads/products'),
            imagesPublicPrefix: '/uploads/products',
            imageDownloadTimeout: (float) self::env('IMAGE_DOWNLOAD_TIMEOUT', '15'),
            corsAllowOrigin: self::env('CORS_ALLOW_ORIGIN', '*'),
            cacheDir: $rootDir.'/var/cache',
            openApiSpecPath: $rootDir.'/resources/openapi.yaml',
        );
    }

    public function isProduction(): bool
    {
        return 'prod' === $this->env;
    }

    private static function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return false === $value || '' === $value ? $default : $value;
    }
}
