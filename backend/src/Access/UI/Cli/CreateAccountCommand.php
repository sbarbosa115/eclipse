<?php

namespace App\Access\UI\Cli;

use App\Access\Application\Command\SignUp;
use App\Access\UI\Http\Input\SignUpInput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\UI\Http\ApiValidationException;
use App\Shared\UI\Http\InputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The sign-up of /registro from the shell: a company and its owner, in any environment. Validated by the form's own
 * SignUpInput. The password is asked hidden (or read from stdin with --password-stdin), never taken as an option, so
 * it stays out of the shell history.
 */
#[AsCommand('app:account:create', 'Creates a company and its owner: the sign-up, from the console.')]
final class CreateAccountCommand extends Command
{
    private const FIELDS = ['company' => 'Company name (razón social)', 'nit' => 'NIT, without the verification digit', 'owner' => 'Owner name', 'email' => 'Owner e-mail'];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly InputMapper $inputs,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        foreach (self::FIELDS as $name => $description) {
            $this->addOption($name, null, InputOption::VALUE_REQUIRED, $description);
        }
        $this->addOption('password-stdin', null, InputOption::VALUE_NONE, 'Read the password from standard input');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        foreach (self::FIELDS as $name => $question) {
            if (null === $input->getOption($name)) {
                $input->setOption($name, $io->ask($question));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $this->password($input, $io);
        if (null === $password) {
            return Command::INVALID;
        }

        try {
            $form = $this->inputs->map([
                'company_name' => (string) $input->getOption('company'),
                'nit' => (string) $input->getOption('nit'),
                'owner_name' => (string) $input->getOption('owner'),
                'email' => (string) $input->getOption('email'),
                'password' => $password,
            ], SignUpInput::class);
            $this->commands->dispatch(new SignUp($form->companyName, $form->nit, $form->ownerName, $form->email, $form->password));
        } catch (ApiValidationException $e) {
            $io->error(array_map(static fn (array $v) => \sprintf('%s: %s', $v['field'], $v['message']), $e->getViolations()));

            return Command::INVALID;
        } catch (InvalidValue $e) {
            $io->error(\sprintf('%s: %s', $e->field(), $e->getMessage()));

            return Command::INVALID;
        }

        $io->success(\sprintf('%s (NIT %s) created; its owner %s signs in at /ingresar.', $form->companyName, $form->nit, $form->email));

        return Command::SUCCESS;
    }

    private function password(InputInterface $input, SymfonyStyle $io): ?string
    {
        if ($input->getOption('password-stdin')) {
            $stream = ($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? \STDIN;
            $password = rtrim((string) stream_get_contents($stream), "\r\n");
            if ('' === $password) {
                $io->error('No password on standard input.');

                return null;
            }

            return $password;
        }
        if (!$input->isInteractive()) {
            $io->error('Pass --password-stdin or run interactively.');

            return null;
        }

        $password = (string) $io->askHidden('Password (at least 10 characters)');
        if ($password !== (string) $io->askHidden('Password again')) {
            $io->error('The passwords do not match.');

            return null;
        }

        return $password;
    }
}
