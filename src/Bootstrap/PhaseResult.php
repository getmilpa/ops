<?php

/**
 * La fase como dato: su nombre, si pasó y su error.
 *
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
 * The outcome of executing a single bootstrap phase: whether it succeeded,
 * and the error message if it failed.
 */
final readonly class PhaseResult
{
    public function __construct(
        public string $name,
        public bool $ok,
        public ?string $error = null,
    ) {
    }

    /**
     * La fase como dato: su nombre, si pasó y su error.
     *
     * @return array{phase: string, ok: bool, error: ?string}
     */
    public function toArray(): array
    {
        return ['phase' => $this->name, 'ok' => $this->ok, 'error' => $this->error];
    }
}
