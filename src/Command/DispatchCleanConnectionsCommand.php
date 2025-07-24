<?php

namespace App\Command;

use App\Message\CleanConnections;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'tehou:dispatch-clean-connections',
    description: 'Dispatches a message to clean expired connections.',
)]
class DispatchCleanConnectionsCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new CleanConnections());

        $output->writeln('Clean connections message dispatched.');

        return Command::SUCCESS;
    }
}
