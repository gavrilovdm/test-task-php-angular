<?php

declare(strict_types=1);

namespace App\Console;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

/** Queue worker: consumes import messages from RabbitMQ (Symfony Messenger Worker). */
#[AsCommand(name: 'messenger:consume', description: 'Consume messages from the async transport')]
final class ConsumeMessagesCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Stop after N messages')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Stop after N seconds')
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Stop when memory exceeds (e.g. 256M)', '256M');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var TransportInterface $transport */
        $transport = $this->container->get('messenger.transport.async');

        $dispatcher = new EventDispatcher();
        if (is_numeric($input->getOption('limit'))) {
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener((int) $input->getOption('limit'), $this->logger));
        }
        if (is_numeric($input->getOption('time-limit'))) {
            $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener((int) $input->getOption('time-limit'), $this->logger));
        }
        $memory = (string) $input->getOption('memory-limit');
        $dispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(self::toBytes($memory), $this->logger));

        $output->writeln('<info>Consuming messages from "async" transport...</info>');
        (new Worker(['async' => $transport], $this->bus, $dispatcher, $this->logger))->run(['sleep' => 500_000]);

        return Command::SUCCESS;
    }

    private static function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
