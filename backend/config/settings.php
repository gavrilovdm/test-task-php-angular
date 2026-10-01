<?php

declare(strict_types=1);

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);

    return false === $value || '' === $value ? $default : $value;
};

$root = dirname(__DIR__);

return [
    'env' => $env('APP_ENV', 'prod'),
    'root' => $root,
    'database_url' => $env('DATABASE_URL', 'postgresql://app:app_secret@db:5432/products'),
    'messenger_dsn' => $env('MESSENGER_TRANSPORT_DSN', 'amqp://app:app_secret@rabbitmq:5672/%2f/messages'),
    'jwt' => [
        'secret' => $env('JWT_SECRET', 'insecure-development-secret-change-me-please'),
        'ttl' => (int) $env('JWT_TTL', '3600'),
    ],
    'admin' => [
        'email' => $env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => $env('ADMIN_PASSWORD', 'admin123'),
    ],
    'import' => [
        'max_file_size' => (int) $env('IMPORT_MAX_FILE_SIZE', (string) (10 * 1024 * 1024)),
        'rate_limit' => (int) $env('IMPORT_RATE_LIMIT', '5'),
        'rate_interval' => $env('IMPORT_RATE_INTERVAL', '1 minute'),
        'dir' => $root.'/var/imports',
    ],
    'images' => [
        'dir' => $root.'/public/uploads/products',
        'public_prefix' => '/uploads/products',
        'timeout' => (float) $env('IMAGE_DOWNLOAD_TIMEOUT', '15'),
    ],
    'cors_allow_origin' => $env('CORS_ALLOW_ORIGIN', '*'),
    'cache_dir' => $root.'/var/cache',
    'openapi_spec' => $root.'/resources/openapi.yaml',
];
