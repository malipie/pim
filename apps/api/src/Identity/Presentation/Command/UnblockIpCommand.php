<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

use const FILTER_VALIDATE_IP;

/**
 * Operator escape hatch: clears the rate-limiter buckets keyed on a
 * given IP across the auth surface (#97 / 0.11.2).
 *
 * Symfony's RateLimiter persists per-key state in the cache pool. The
 * fastest way to reset a single principal is `RateLimiterFactory::create($key)
 * ->reset()` — the limiter implementations override `reset()` to clear
 * exactly that key without touching others. We hit both auth limiters
 * keyed on IP so a forgiveness flow does not need a separate command
 * per endpoint.
 */
#[AsCommand(
    name: 'pim:security:unblock-ip',
    description: 'Reset rate-limiter buckets for a given IP across auth endpoints.',
)]
final class UnblockIpCommand extends Command
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $authLoginLimiter,
        private readonly RateLimiterFactoryInterface $authRefreshLimiter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('ip', InputArgument::REQUIRED, 'IP address to forgive (matches the limiter key).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $ip */
        $ip = $input->getArgument('ip');

        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            $io->error(\sprintf('"%s" is not a valid IP address.', $ip));

            return Command::INVALID;
        }

        $this->authLoginLimiter->create($ip)->reset();
        $this->authRefreshLimiter->create($ip)->reset();

        $io->success(\sprintf('Rate-limiter buckets cleared for IP %s on auth_login + auth_refresh.', $ip));

        return Command::SUCCESS;
    }
}
