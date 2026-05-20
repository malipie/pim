<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Agent\CmdKPlanner;
use App\Identity\Domain\Attribute\RequiresPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * VIEW-19 (#550) — Cmd+K command planner endpoint.
 *
 * MVP demo path: deterministic regex parser. Real Anthropic Claude
 * Sonnet 4.5 + tool-use planning lands in epik 0.7 (Faza 2) — that
 * extension swaps the planner implementation behind the same contract,
 * with BYOK key rotation + Mercure SSE streaming.
 *
 * Response contract:
 *   {
 *     "action": "set_attribute",
 *     "payload": { "attr": "brand", "value": "Festo" },
 *     "summary": "Ustaw brand = Festo",
 *     "selection_context": { "selected_ids": [...], "total_matching": 247 }
 *   }
 *
 * Unmatched commands return 200 with `action: null` so the FE can show
 * a friendly fallback chip („dodaj Anthropic w VIEW-19.1").
 */
final class CmdKController
{
    public function __construct(
        private readonly CmdKPlanner $planner,
    ) {
    }

    #[Route('/api/agent/cmd-k', name: 'pim_cmd_k_plan', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'agent', action: 'bulk_actions')]
    public function plan(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $command = $body['command'] ?? null;
        if (!\is_string($command) || '' === trim($command)) {
            throw new BadRequestHttpException('command must be a non-empty string.');
        }

        $selectionContext = $body['selection_context'] ?? null;
        if (!\is_array($selectionContext)) {
            $selectionContext = ['selected_ids' => [], 'total_matching' => 0];
        }

        $plan = $this->planner->plan($command);
        if (null === $plan) {
            return new JsonResponse([
                'action' => null,
                'payload' => null,
                'summary' => null,
                'fallback_hint' => 'Komenda nie pasuje do żadnego z 6 MVP intentów. Anthropic SDK + free-form parsing landują w VIEW-19.1 (Faza 2).',
                'selection_context' => $selectionContext,
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'action' => $plan['action'],
            'payload' => $plan['payload'],
            'summary' => $plan['summary'],
            'selection_context' => $selectionContext,
        ]);
    }
}
