<?php

declare(strict_types=1);

namespace App\Console;

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\ConsoleRunner as MigrationsConsoleRunner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;

final class ConsoleApplicationFactory
{
    public static function create(ContainerInterface $container): Application
    {
        $application = new Application('Products Import Console', '1.0');

        $dependencyFactory = DependencyFactory::fromEntityManager(
            new PhpFile(__DIR__.'/../../config/migrations.php'),
            new ExistingEntityManager($container->get(EntityManagerInterface::class)),
        );
        MigrationsConsoleRunner::addCommands($application, $dependencyFactory);

        $application->addCommands([
            $container->get(ConsumeMessagesCommand::class),
            $container->get(LoadFixturesCommand::class),
            $container->get(ImportFileCommand::class),
        ]);

        return $application;
    }
}
