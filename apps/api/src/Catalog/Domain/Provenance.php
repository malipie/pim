<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Where a single ObjectValue came from.
 *
 * Per CLAUDE.md "Architecture rules" point 5 — every value carries
 * provenance metadata so admins can answer "who/what set this field?"
 * Three sources are usable in MVP:
 *
 *   - `Manual`     — admin set it via the admin UI form.
 *   - `Import`     — a bulk import job (CSV / XLSX, future) wrote it.
 *   - `Integration`— an upstream system pushed it via an integration
 *                    adapter (BaseLinker / Shopify, phase 1).
 *
 * `Agent` is reserved for phase 2 (epic 0.7) — when the agent layer
 * lands, this enum gains a fourth case + the admin gets an "approval
 * inbox" for agent-authored changes. For now we do NOT add the case
 * here so a stale fixture cannot accidentally claim agent provenance.
 *
 * `provenance_meta JSONB` on ObjectValue carries free-form context
 * (importer job id, integration profile, etc.) — this enum is the
 * coarse classifier used in filters and provenance badges in the UI
 * (epic 0.6 #61).
 */
enum Provenance: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Integration = 'integration';
}
