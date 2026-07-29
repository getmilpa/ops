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
 * Contract for plugins that contribute scheduled tasks — the cron-domain counterpart to
 * `ToolProviderInterface` (`milpa/core`, `Milpa\Interfaces\Tooling\ToolProviderInterface`):
 * same shape, same idea, one method returning the plugin's contribution. A host command
 * (Task 11) collects `cronTasks()` from every booted plugin implementing this interface and
 * registers them into a {@see CronRegistry} alongside the host's own `config/cron.php` tasks.
 */
interface CronProviderInterface
{
    /**
     * The tasks this plugin wants scheduled. Called during cron registry assembly, not
     * during plugin boot — implementations should build and return the list, not register
     * it anywhere themselves.
     *
     * @return list<TaskDefinition>
     */
    public function cronTasks(): array;
}
