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

namespace Milpa\Ops\Deploy;

/**
 * One ordered step of taking a built system live — build images, start services, bootstrap the
 * database, health-check. The step owns its own effect and throws on failure; the {@see DeployRunner}
 * sequences steps fail-fast and turns each into a {@see StepResult}.
 *
 * Domain-blind: the runner knows nothing of what a step does — the host supplies steps that shell
 * out to docker compose, run coa commands, probe HTTP, etc. (mechanism here, content in the host —
 * ADR-0023).
 */
interface DeployStepInterface
{
    /** A short stable identifier for the step (e.g. "build", "up", "bootstrap", "health"). */
    public function name(): string;

    /** Perform the step's effect. MUST throw on failure so the runner can stop fail-fast. */
    public function run(): void;
}
