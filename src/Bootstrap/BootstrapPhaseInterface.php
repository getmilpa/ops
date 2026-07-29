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

namespace Milpa\Ops\Bootstrap;

/**
 * One ordered step of taking a fresh system to an operable state — schema, plugin registration,
 * install hooks, migrations, seeds. The phase owns its own effect and throws on failure; the
 * {@see BootstrapRunner} sequences phases fail-fast and turns each into a {@see PhaseResult}.
 *
 * Domain-blind: the runner knows nothing of what a phase does — the host supplies phases that
 * touch Doctrine, the plugin registry, the kernel, etc. (mechanism here, content in the host —
 * ADR-0023).
 */
interface BootstrapPhaseInterface
{
    /** A short stable identifier for the phase (e.g. "schema", "rows"). */
    public function name(): string;

    /** Perform the phase's effect. MUST throw on failure so the runner can stop fail-fast. */
    public function run(): void;
}
