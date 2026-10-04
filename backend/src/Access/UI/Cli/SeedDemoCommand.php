<?php

namespace App\Access\UI\Cli;

use App\Access\Application\Command\SignUp;
use App\Access\Application\Query\Users;
use App\Shared\Application\Command\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The demo company of a local stack and the smoke suite (docs/tests/ui-regression.md, "Before you start"). Refuses to
 * run in production: its password is in the repository.
 */
#[AsCommand('app:demo:seed', 'Signs up the demo company and its owner (development and smoke runs only).')]
final class SeedDemoCommand extends Command
{
    public const EMAIL = 'demo@mustang.test';
    public const PASSWORD = 'mustang-demo-123';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly Users $users,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('The demo company is for development only.');

            return Command::FAILURE;
        }
        if (null !== $this->users->byEmail(self::EMAIL)) {
            $io->note('The demo company already exists.');

            return Command::SUCCESS;
        }

        $this->commands->dispatch(new SignUp('Comercializadora Demo S.A.S.', '900123456', 'Dueña Demo', self::EMAIL, self::PASSWORD));
        $io->success(\sprintf('Demo company created: %s / %s', self::EMAIL, self::PASSWORD));

        return Command::SUCCESS;
    }
}
