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

use Milpa\Ops\Support\ReportInterface;

/**
 * El resultado de un arranque: qué fases corrieron, cuál falló y dónde quedó parado.
 */
final class BootstrapReport implements ReportInterface
{
    /**
     * @param list<PhaseResult> $results
     */
    public function __construct(private readonly array $results)
    {
    }

    /**
     * @return list<PhaseResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /** True cuando alguna fase falló. Un host que arranca con esto en true está a medio preparar. */
    public function hasFindings(): bool
    {
        foreach ($this->results as $r) {
            if (!$r->ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las fases como lista, cada una con su nombre, si pasó y su error.
     *
     * @return array{phases: list<array{phase: string, ok: bool, error: ?string}>}
     */
    public function toArray(): array
    {
        return ['phases' => array_map(static fn (PhaseResult $r): array => $r->toArray(), $this->results)];
    }
}
