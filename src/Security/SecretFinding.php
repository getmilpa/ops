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

/** One secret matched by a {@see SecretScanner} rule: which rule, where, and the redacted line. */
final readonly class SecretFinding
{
    public function __construct(
        public string $ruleId,
        public string $severity,
        public string $file,
        public int $line,
        public string $excerpt,
    ) {
    }

    /**
     * El hallazgo como dato, con el extracto YA redactado — el secreto completo nunca entra al reporte, porque el reporte se guarda, se sube a CI y se pega en tickets.
     *
     * @return array{rule: string, severity: string, file: string, line: int, excerpt: string}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
            'excerpt' => $this->excerpt,
        ];
    }
}
