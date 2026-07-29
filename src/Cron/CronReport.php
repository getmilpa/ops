<?php

/**
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

namespace Milpa\Ops\Cron;

use Milpa\Ops\Support\ReportInterface;

/**
 * The outcome of one {@see CronRunner::run()} call: one {@see TaskResult} per task that
 * was due, in the order the runner executed them. `hasFindings()` is true when any task
 * failed, so a host command (Task 11's `cron:run`) exits non-zero the same way it would
 * on a secret or a failed backup ({@see ReportInterface}).
 */
final readonly class CronReport implements ReportInterface
{
    /**
     * @param list<TaskResult> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    /** True cuando alguna tarea programada terminó con error. */
    public function hasFindings(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las tareas corridas como lista, cada una con su resultado.
     *
     * @return array{results: list<array{name: string, ok: bool, seconds: float, error: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'results' => array_map(static fn (TaskResult $r): array => $r->toArray(), $this->results),
        ];
    }
}
