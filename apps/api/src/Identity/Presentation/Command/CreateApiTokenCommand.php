<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use App\Identity\Domain\Entity\ApiToken;
use App\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\RbacApiTokenAuthenticator;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use const DATE_ATOM;

/**
 * RBAC-P2-003 (#652) — mint an ApiToken for a User. Plaintext token
 * printed to stdout exactly once; only the SHA-256 hash is persisted.
 *
 * Phase 5 #699/#700 add the Settings UI for token CRUD. This CLI command
 * is the Phase 2 testability enabler (and the production fallback for
 * tenant onboarding before the UI ships).
 *
 * Token format: cortex_<tenant_short>_<random32>. The `cortex_` prefix
 * lets gitleaks / TruffleHog detect leaks in CI.
 */
#[AsCommand(
    name: 'cortex:apitoken:create',
    description: 'Mint an RBAC ApiToken dla User (plaintext printed once)',
)]
final class CreateApiTokenCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ApiTokenRepositoryInterface $apiTokens,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('name', InputArgument::REQUIRED, 'Token label (visible w Phase 5 UI)')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Scope codes (repeat for multiple)', ['read-only'])
            ->addOption('ttl-days', null, InputOption::VALUE_REQUIRED, 'Days until expiry (omit for never)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $name */
        $name = $input->getArgument('name');
        /** @var list<string> $scopes */
        $scopes = $input->getOption('scope');
        $ttlDays = $input->getOption('ttl-days');

        $user = $this->users->findByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('User %s not found.', $email));

            return Command::FAILURE;
        }

        $tenant = $user->getTenant();
        $tenantShort = substr($tenant->getCode(), 0, 8);
        $plaintext = RbacApiTokenAuthenticator::generatePlaintext($tenantShort);
        $tokenHash = RbacApiTokenAuthenticator::hashFor($plaintext);
        $last4 = RbacApiTokenAuthenticator::last4($plaintext);

        $expiresAt = null;
        if (null !== $ttlDays && '' !== $ttlDays) {
            $expiresAt = new DateTimeImmutable(\sprintf('+%d days', (int) $ttlDays));
        }

        $token = new ApiToken(
            tenantId: $tenant->getId(),
            userId: $user->getId(),
            name: $name,
            tokenHash: $tokenHash,
            tokenLast4: $last4,
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
        $this->apiTokens->save($token);

        $io->success(\sprintf('Token "%s" minted dla user %s (tenant %s)', $name, $email, $tenant->getCode()));
        $io->section('Plaintext token — copy NOW; this is the only time it is shown');
        $output->writeln($plaintext);
        $io->newLine();
        $io->note([
            \sprintf('Token ID:    %s', $token->getId()->toRfc4122()),
            \sprintf('Last 4:      ...%s', $last4),
            \sprintf('Scopes:      %s', implode(', ', $scopes)),
            \sprintf('Expires:     %s', null !== $expiresAt ? $expiresAt->format(DATE_ATOM) : 'never'),
            'Authentication header: Authorization: Token '.$plaintext,
        ]);

        return Command::SUCCESS;
    }
}
