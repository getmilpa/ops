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

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes every {@see TaskDefinition} due at a given instant. Each due task is timed and run
 * in isolation: a `Throwable` from one callback is caught, recorded as a failing
 * {@see TaskResult}, optionally logged, and never stops the runner from moving on to the next
 * due task. A task that is not due at `$now` (see {@see CronRegistry::due()}) is never invoked
 * and never appears in the resulting {@see CronReport}.
 */
final class CronRunner
{
    public function __construct(
        private readonly CronRegistry $registry,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Runs every task due at `$now`, in registration order, and reports what happened.
     * Never throws itself — a failing task's exception is captured in its `TaskResult`
     * instead of propagating out of `run()`.
     */
    public function run(DateTimeImmutable $now): CronReport
    {
        $results = [];

        foreach ($this->registry->due($now) as $task) {
            $results[] = $this->runOne($task);
        }

        return new CronReport($results);
    }

    private function runOne(TaskDefinition $task): TaskResult
    {
        $start = microtime(true);

        try {
            ($task->callback)();

            return new TaskResult($task->name, true, microtime(true) - $start);
        } catch (Throwable $e) {
            $seconds = microtime(true) - $start;

            $this->logger?->error(sprintf('Cron task "%s" failed: %s', $task->name, $e->getMessage()), [
                'task' => $task->name,
                'exception' => $e,
            ]);

            return new TaskResult($task->name, false, $seconds, $e->getMessage());
        }
    }
}
