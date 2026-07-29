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

use Milpa\Ops\Support\ReportInterface;

/**
 * The security-domain report: secrets found by {@see SecretScanner}, advisories found by
 * {@see AuditParser}, and permission violations found by {@see PermissionChecker} — always via
 * optional constructor params, to stay BC-friendly.
 */
final class SecurityReport implements ReportInterface
{
    /**
     * @param list<SecretFinding>     $secrets
     * @param list<AdvisoryFinding>   $advisories
     * @param list<PermissionFinding> $permissions
     */
    public function __construct(
        private readonly array $secrets,
        private readonly array $advisories = [],
        private readonly array $permissions = [],
    ) {
    }

    /** @return list<SecretFinding> */
    public function findings(): array
    {
        return $this->secrets;
    }

    /** @return list<AdvisoryFinding> */
    public function advisories(): array
    {
        return $this->advisories;
    }

    /** @return list<PermissionFinding> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** True cuando hay algo que atender: un secreto, un aviso de seguridad o un permiso mal puesto. */
    public function hasFindings(): bool
    {
        return $this->secrets !== [] || $this->advisories !== [] || $this->permissions !== [];
    }

    /**
     * Los hallazgos como dato, agrupados por tipo. Los secretos van con el extracto YA redactado.
     *
     * @return array{
     *     secrets: list<array{rule: string, severity: string, file: string, line: int, excerpt: string}>,
     *     advisories: list<array{package: string, severity: string, title: string, cve: ?string, advisory: string}>,
     *     permissions: list<array{path: string, actual_mode: string, max_mode: string, severity: string, label: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'secrets' => array_map(static fn (SecretFinding $f): array => $f->toArray(), $this->secrets),
            'advisories' => array_map(static fn (AdvisoryFinding $a): array => $a->toArray(), $this->advisories),
            'permissions' => array_map(static fn (PermissionFinding $p): array => $p->toArray(), $this->permissions),
        ];
    }
}
