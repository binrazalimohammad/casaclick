<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-time (or occasional) use: mark existing users as email-verified after enabling
 * verification checks, so legacy accounts can sign in without receiving a new link.
 */
#[AsCommand(
    name: 'app:verify-legacy-user-emails',
    description: 'Set email_verified=true (and clear verification tokens) for users still marked unverified.',
)]
class VerifyLegacyUserEmailsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show how many rows would be updated without changing the database.');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation (for scripts/CI).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $countDql = 'SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.emailVerified = false';
        $count = (int) $this->em->createQuery($countDql)->getSingleScalarResult();

        if ($count === 0) {
            $io->success('No unverified users found. Nothing to do.');

            return Command::SUCCESS;
        }

        $io->note(sprintf('Found %d user(s) with email_verified = false.', $count));

        if ($input->getOption('dry-run')) {
            $io->warning('Dry run only — no changes were made. Run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('yes') && !$io->confirm('Mark all these users as email-verified and clear their verification tokens?', false)) {
            $io->info('Cancelled.');

            return Command::FAILURE;
        }

        $this->em->createQuery(
            'UPDATE App\Entity\User u SET u.emailVerified = true, u.verificationToken = null WHERE u.emailVerified = false'
        )->execute();

        $io->success(sprintf('Updated %d user(s).', $count));

        return Command::SUCCESS;
    }
}
