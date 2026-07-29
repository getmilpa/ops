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

/**
 * An in-memory collection of {@see TaskDefinition}s. A host command (Task 11) assembles one
 * from `config/cron.php` plus every booted plugin's {@see CronProviderInterface::cronTasks()},
 * then hands it to {@see CronRunner} (Task 10) to execute whatever is due.
 */
final class CronRegistry
{
    /** @var list<TaskDefinition> */
    private array $tasks = [];

    /** Agrega una tarea al registro. Registrar no la corre ni la programa: sólo la hace visible a {@see self::due()}. */
    public function register(TaskDefinition $task): void
    {
        $this->tasks[] = $task;
    }

    /** @return list<TaskDefinition> Every registered task, in registration order. */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * Las tareas que toca correr en `$now`.
     *
     * Recibir el instante en vez de leer el reloj es lo que deja probar esto sin esperar: el
     * registro no sabe qué hora es, se lo dicen.
     *
     * @return list<TaskDefinition> The subset of registered tasks due to run at `$now`.
     */
    public function due(DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $this->tasks,
            static fn (TaskDefinition $task): bool => $task->isDue($now),
        ));
    }
}
