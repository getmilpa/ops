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

use Psr\Log\LoggerInterface;

/**
 * Orchestrates an ordered sequence of deploy steps fail-fast, turning each step's
 * outcome into a structured report suitable for logging, CI integration, and host-level
 * decision-making (abort the deployment if critical steps fail).
 *
 * Domain-blind: steps own their own effects (docker compose, coa commands, HTTP probes, etc.).
 * The runner is pure orchestration.
 */
final class DeployRunner
{
    /** @param list<DeployStepInterface> $steps */
    public function __construct(
        private readonly array $steps,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Corre las pasos en orden y se detiene en la primera que falla.
     *
     * Fail-fast y no «corre todo y reporta»: un paso que falla suele dejar el sistema
     * a medio preparar, y seguir adelante produce errores posteriores que apuntan al lugar
     * equivocado. El reporte trae el paso que falló y las que alcanzaron a correr, para
     * que quien lo lea sepa dónde quedó parado.
     *
     * No lanza: el fallo viaja en el reporte, y quien llama decide si aborta.
     */
    public function run(): DeployReport
    {
        $results = [];
        foreach ($this->steps as $step) {
            try {
                $step->run();
                $results[] = new StepResult($step->name(), true, null);
            } catch (\Throwable $e) {
                $this->logger?->error('deploy step failed', ['step' => $step->name(), 'exception' => $e]);
                $results[] = new StepResult($step->name(), false, $e->getMessage());
                break; // fail-fast: no step after a failure can succeed.
            }
        }

        return new DeployReport($results);
    }
}
