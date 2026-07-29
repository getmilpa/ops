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

use Psr\Log\LoggerInterface;

/**
 * Orchestrates an ordered sequence of bootstrap phases fail-fast, turning each phase's
 * outcome into a structured report suitable for logging, CI integration, and host-level
 * decision-making (abort the app if critical phases fail).
 *
 * Domain-blind: phases own their own effects (Doctrine schema creation, plugin registration,
 * etc.). The runner is pure orchestration.
 */
final class BootstrapRunner
{
    /**
     * @param list<BootstrapPhaseInterface> $phases
     */
    public function __construct(
        private readonly array $phases,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Corre las fases en orden y se detiene en la primera que falla.
     *
     * Fail-fast y no «corre todo y reporta»: una fase que falla suele dejar el sistema
     * a medio preparar, y seguir adelante produce errores posteriores que apuntan al lugar
     * equivocado. El reporte trae la fase que falló y las que alcanzaron a correr, para
     * que quien lo lea sepa dónde quedó parado.
     *
     * No lanza: el fallo viaja en el reporte, y quien llama decide si aborta.
     */
    public function run(): BootstrapReport
    {
        $results = [];
        foreach ($this->phases as $phase) {
            try {
                $phase->run();
                $results[] = new PhaseResult($phase->name(), true, null);
            } catch (\Throwable $e) {
                $this->logger?->error('bootstrap phase failed', ['phase' => $phase->name(), 'exception' => $e]);
                $results[] = new PhaseResult($phase->name(), false, $e->getMessage());
                break; // fail-fast: no phase after a failure can succeed.
            }
        }

        return new BootstrapReport($results);
    }
}
