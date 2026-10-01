<?php

declare(strict_types=1);

use App\Tests\Support\TestContainer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

require dirname(__DIR__).'/vendor/autoload.php';

// Recreate the schema of the test database once per run (DATABASE_URL must point to the *_test DB).
$databaseUrl = (string) getenv('DATABASE_URL');
if (!str_contains($databaseUrl, '_test')) {
    fwrite(\STDERR, "Refusing to run tests: DATABASE_URL must point to a *_test database, got \"{$databaseUrl}\".\n");
    exit(1);
}

$em = TestContainer::create()->get(EntityManagerInterface::class);
$schemaTool = new SchemaTool($em);
$schemaTool->dropDatabase();
$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
