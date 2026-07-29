<?php

/**
 * El paso como dato: su nombre, si pasó y su error.
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

namespace Milpa\Ops\Deploy;

/**
 * The outcome of executing a single deploy step: whether it succeeded, and the error if it failed.
 */
final readonly class StepResult
{
    public function __construct(
        public string $name,
        public bool $ok,
        public ?string $error = null,
    ) {
    }

    /**
     * El paso como dato: su nombre, si pasó y su error.
     *
     * @return array{step: string, ok: bool, error: ?string}
     */
    public function toArray(): array
    {
        return ['step' => $this->name, 'ok' => $this->ok, 'error' => $this->error];
    }
}
