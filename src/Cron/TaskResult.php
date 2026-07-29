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

/**
 * The outcome of {@see CronRunner} executing one due {@see TaskDefinition}: whether its
 * callback completed without throwing, how long it took, and — when it threw — the
 * exception message. A task that was never due (see {@see CronRegistry::due()}) never
 * gets a `TaskResult` at all; this only exists for tasks the runner actually invoked.
 */
final readonly class TaskResult
{
    public function __construct(
        public string $name,
        public bool $ok,
        public float $seconds,
        public ?string $error = null,
    ) {
    }

    /**
     * La corrida de una tarea como dato: cuál, si pasó y qué dijo.
     *
     * @return array{name: string, ok: bool, seconds: float, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'ok' => $this->ok,
            'seconds' => $this->seconds,
            'error' => $this->error,
        ];
    }
}
