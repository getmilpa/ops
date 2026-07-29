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

use Closure;
use DateTimeImmutable;

/**
 * A named unit of scheduled work: a human-readable `$name`, the cron expression that gates
 * when it runs, and the `$callback` a runner ({@see CronRunner}, Task 10) invokes when it's
 * due. Hosts and plugins ({@see CronProviderInterface}) construct these; this package never
 * runs the callback itself — {@see self::isDue()} only answers "should this run now?".
 */
final class TaskDefinition
{
    private readonly string $expression;

    private readonly CronExpression $cronExpression;

    /**
     * @throws \InvalidArgumentException if `$expression` is not a valid 5-field cron
     *                                   expression — see {@see CronExpression::__construct()}.
     */
    public function __construct(
        public readonly string $name,
        string $expression,
        public readonly Closure $callback,
        public readonly string $description = '',
    ) {
        $this->expression = $expression;
        $this->cronExpression = new CronExpression($expression);
    }

    /**
     * The raw cron expression string this task was defined with, e.g. `0 3 * * *`. Callers
     * that render a task list (Task 11's `cron:list`) use this alongside `$name`.
     */
    public function expression(): string
    {
        return $this->expression;
    }

    /**
     * True when `$now` matches this task's cron expression. Delegates entirely to
     * {@see CronExpression::isDue()} — this method exists so callers ({@see CronRegistry})
     * work against `TaskDefinition` without reaching into its expression.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        return $this->cronExpression->isDue($now);
    }
}
