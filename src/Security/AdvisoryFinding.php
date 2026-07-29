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

/** One security advisory reported by `composer audit` for an installed dependency, as parsed by {@see AuditParser}. */
final readonly class AdvisoryFinding
{
    public function __construct(
        public string $package,
        public string $severity,
        public string $title,
        public ?string $cve,
        public string $advisoryId,
    ) {
    }

    /**
     * El aviso como dato: paquete, versión afectada y severidad.
     *
     * @return array{package: string, severity: string, title: string, cve: ?string, advisory: string}
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'severity' => $this->severity,
            'title' => $this->title,
            'cve' => $this->cve,
            'advisory' => $this->advisoryId,
        ];
    }
}
