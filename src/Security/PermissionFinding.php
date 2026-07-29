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

namespace Milpa\Ops\Security;

/** One file whose actual mode carries a bit that its {@see PermissionRule} forbids. */
final readonly class PermissionFinding
{
    public function __construct(
        public string $path,
        public int $actualMode,
        public int $maxMode,
        public string $severity,
        public string $label,
    ) {
    }

    /**
     * El permiso mal puesto como dato: la ruta, lo que tiene y lo que debería tener.
     *
     * @return array{path: string, actual_mode: string, max_mode: string, severity: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'actual_mode' => sprintf('%04o', $this->actualMode),
            'max_mode' => sprintf('%04o', $this->maxMode),
            'severity' => $this->severity,
            'label' => $this->label,
        ];
    }
}
