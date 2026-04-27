<?php

declare(strict_types=1);

namespace App\Observability\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prometheus-compatible scrape endpoint.
 *
 * Sprint 0 ships only the metric called out as the runtime guardrail in the
 * architecture — `frankenphp_worker_memory_bytes` — plus a couple of trivially
 * derivable companions. A full pipeline (Prometheus scrape config, alertmanager
 * thresholds, messenger consumer lag, business metrics) lands in epik 0.11
 * (#103-#105). The endpoint stays available in dev so the bulk-import
 * benchmark (ticket 0.0.13) has a runtime counterpart for ad-hoc inspection.
 *
 * Numbers reported are "memory of whichever worker handled THIS scrape" — fine
 * for single-worker dev. In production with multiple FrankenPHP workers behind
 * the edge Caddy, scraping reaches one randomly, so the alert threshold is set
 * on the rolling max across scrapes.
 */
final class MetricsController
{
    #[Route(path: '/api/metrics', name: 'app_metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $resident = \memory_get_usage(true);
        $peak = \memory_get_peak_usage(true);
        $pid = \getmypid();

        $body = <<<METRICS
            # HELP frankenphp_worker_memory_bytes Resident PHP memory of the FrankenPHP worker that handled the scrape.
            # TYPE frankenphp_worker_memory_bytes gauge
            frankenphp_worker_memory_bytes {$resident}
            # HELP frankenphp_worker_peak_memory_bytes Peak PHP memory ever held by this worker since boot.
            # TYPE frankenphp_worker_peak_memory_bytes gauge
            frankenphp_worker_peak_memory_bytes {$peak}
            # HELP frankenphp_worker_pid Process id of the worker that handled the scrape.
            # TYPE frankenphp_worker_pid gauge
            frankenphp_worker_pid {$pid}

            METRICS;

        return new Response($body, Response::HTTP_OK, ['content-type' => 'text/plain; version=0.0.4']);
    }
}
