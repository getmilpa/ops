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

use Milpa\Ops\Support\ReportInterface;

/**
 * El resultado de un despliegue: qué pasos corrieron, cuál falló y dónde quedó parado.
 */
final class DeployReport implements ReportInterface
{
    /** @param list<StepResult> $results */
    public function __construct(private readonly array $results)
    {
    }

    /** @return list<StepResult> */
    public function results(): array
    {
        return $this->results;
    }

    /** True cuando algún paso del despliegue falló. */
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
     * Los pasos como lista, cada uno con su nombre, si pasó y su error.
     *
     * @return array{steps: list<array{step: string, ok: bool, error: ?string}>}
     */
    public function toArray(): array
    {
        return ['steps' => array_map(static fn (StepResult $r): array => $r->toArray(), $this->results)];
    }
}
