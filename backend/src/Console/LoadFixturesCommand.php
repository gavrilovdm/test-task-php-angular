<?php

declare(strict_types=1);

namespace App\Console;

use App\DataFixtures\ProductAttributeFixtures;
use App\DataFixtures\ProductFixtures;
use App\DataFixtures\ProductImageFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'fixtures:load', description: 'Seed products, product_attributes and product_images')]
final class LoadFixturesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Append instead of purging the tables first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loader = new Loader();
        $loader->addFixture(new ProductFixtures());
        $loader->addFixture(new ProductAttributeFixtures());
        $loader->addFixture(new ProductImageFixtures());

        $purger = new ORMPurger($this->em, ['doctrine_migration_versions']);
        $executor = new ORMExecutor($this->em, $purger);
        $executor->setLogger(new ConsoleLogger($output, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL]));
        $executor->execute($loader->getFixtures(), (bool) $input->getOption('append'));

        $output->writeln('<info>Fixtures loaded.</info>');

        return Command::SUCCESS;
    }
}
