<?php

/**
 * El reporte como dato serializable.
 *
 * This file is part of Milpa Ops — the system's metabolism: security,
 * backup, scheduled maintenance and bootstrap for the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/ops
 */

declare(strict_types=1);

namespace Milpa\Ops\Support;

/**
 * The common contract every ops report (security, backup, cron) satisfies so that
 * a host command can render it as a table for humans OR serialize it for agents/CI
 * with one uniform shape, and decide the process exit code from `hasFindings()`.
 */
interface ReportInterface
{
    /**
     * El reporte como dato serializable.
     *
     * @return array<string, mixed> A JSON-serializable view of the report.
     */
    public function toArray(): array;

    /**
     * True when the report carries something the caller must act on — a secret found,
     * a failed backup, a task that errored. Host commands exit non-zero when true.
     */
    public function hasFindings(): bool;
}
