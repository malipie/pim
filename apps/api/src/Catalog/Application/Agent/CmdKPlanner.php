<?php

declare(strict_types=1);

namespace App\Catalog\Application\Agent;

/**
 * VIEW-19 (#550) — rule-based Cmd+K command parser.
 *
 * MVP demo gate: ship the keyboard shortcut + palette UX + plan preview
 * flow with a deterministic regex parser. Real Anthropic SDK + tool use
 * + BYOK key rotation lands in epik 0.7 (Faza 2) — until then the
 * planner covers the six killer intents from PRD §3.5 without an LLM
 * dependency.
 *
 * Supported intents (rule-set tuned to typical Marcin/Magda phrasings):
 *   1. set_attribute        — "ustaw {attr} na {value}"
 *   2. clear_attribute      — "wyczyść {attr}" / "wyzeruj {attr}"
 *   3. multi_attribute_edit — "skopiuj {src} do {dst}" (set dst = $src)
 *   4. increment_numeric    — "pomnóż {attr} przez {n}" / "add|sub|mul|div|mod {n}"
 *   5. add_category         — "dodaj kategorię {code}"
 *   6. publish_channels     — "publikuj na {channel}"
 *
 * Unknown phrasings return `null` so the FE can render a friendly
 * fallback („nie rozumiem — w VIEW-19.1 dodamy Anthropic").
 */
final class CmdKPlanner
{
    /**
     * @return array{action: string, payload: array<string, mixed>, summary: string}|null
     */
    public function plan(string $command): ?array
    {
        $normalised = trim($command);
        if ('' === $normalised) {
            return null;
        }

        // 1) set_attribute — „ustaw {attr} na {value}"
        if (1 === preg_match('/^ustaw\s+([a-z0-9_]+)\s+na\s+(.+)$/iu', $normalised, $m)) {
            return [
                'action' => 'set_attribute',
                'payload' => ['attr' => strtolower($m[1]), 'value' => $this->stripQuotes($m[2])],
                'summary' => \sprintf('Ustaw %s = %s', $m[1], $m[2]),
            ];
        }

        // 2) clear_attribute — „wyczyść {attr}" or „wyzeruj {attr}"
        if (1 === preg_match('/^(wyczy[sś][cć]|wyzeruj|skasuj)\s+([a-z0-9_]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'clear_attribute',
                'payload' => ['attr' => strtolower($m[2])],
                'summary' => \sprintf('Wyczyść %s', $m[2]),
            ];
        }

        // 3) multi_attribute_edit — „skopiuj {src} do {dst}"
        if (1 === preg_match('/^skopiuj\s+([a-z0-9_]+)\s+do\s+([a-z0-9_]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'multi_attribute_edit',
                'payload' => [
                    'edits' => [
                        ['attr' => strtolower($m[2]), 'op' => 'set', 'value' => '__copy_from__'.strtolower($m[1])],
                    ],
                ],
                'summary' => \sprintf('Skopiuj %s → %s', $m[1], $m[2]),
            ];
        }

        // 4) increment_numeric — „pomnóż {attr} przez {n}", „dodaj {n} do {attr}", „odejmij {n} od {attr}"
        if (1 === preg_match('/^pomn[oó][zż]\s+([a-z0-9_]+)\s+przez\s+([0-9.,]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'increment_numeric',
                'payload' => [
                    'attr' => strtolower($m[1]),
                    'operator' => '*',
                    'operand' => (float) str_replace(',', '.', $m[2]),
                ],
                'summary' => \sprintf('%s *= %s', $m[1], $m[2]),
            ];
        }
        if (1 === preg_match('/^dodaj\s+([0-9.,]+)\s+do\s+([a-z0-9_]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'increment_numeric',
                'payload' => [
                    'attr' => strtolower($m[2]),
                    'operator' => '+',
                    'operand' => (float) str_replace(',', '.', $m[1]),
                ],
                'summary' => \sprintf('%s += %s', $m[2], $m[1]),
            ];
        }

        // 5) add_category — „dodaj kategorię {code}"
        if (1 === preg_match('/^dodaj\s+kategori[eę]\s+([a-z0-9_\-]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'add_category',
                'payload' => ['category_codes' => [strtolower($m[1])]],
                'summary' => \sprintf('Dodaj kategorię %s', $m[1]),
            ];
        }

        // 6) publish_channels — „publikuj na {channel}" / „opublikuj na {channel}"
        if (1 === preg_match('/^(o?publikuj)\s+na\s+([a-z0-9_\-]+)$/iu', $normalised, $m)) {
            return [
                'action' => 'publish_channels',
                'payload' => ['channel_codes' => [strtolower($m[2])]],
                'summary' => \sprintf('Publikuj na %s', $m[2]),
            ];
        }

        return null;
    }

    private function stripQuotes(string $raw): string
    {
        $trimmed = trim($raw);
        if (\strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last = $trimmed[\strlen($trimmed) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($trimmed, 1, -1);
            }
        }

        return $trimmed;
    }
}
