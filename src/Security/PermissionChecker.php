<?php

/**
 * Compara los permisos reales de cada ruta contra lo que la regla exige, y devuelve sólo lo que no coincide.
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

namespace Milpa\Ops\Security;

/**
 * Checks a set of {@see PermissionRule}s against the filesystem's actual file modes.
 *
 * A rule's `maxMode` is a ceiling: any bit set on the actual mode that the ceiling does not
 * allow is a violation, regardless of how the file's other bits compare (stricter-than-allowed
 * is always fine). A rule whose file does not exist is silently skipped — a missing file is not
 * a permission violation.
 */
final class PermissionChecker
{
    /**
     * Compara los permisos reales de cada ruta contra lo que la regla exige, y devuelve sólo lo que no coincide.
     *
     * @param list<PermissionRule> $rules
     *
     * @return list<PermissionFinding>
     */
    public function check(array $rules): array
    {
        $findings = [];
        foreach ($rules as $rule) {
            if (!is_file($rule->path)) {
                continue;
            }

            $actual = fileperms($rule->path) & 0777;

            // Any bit set on $actual that $maxMode does not allow is a violation.
            if (($actual & ~$rule->maxMode) !== 0) {
                $findings[] = new PermissionFinding($rule->path, $actual, $rule->maxMode, $rule->severity, $rule->label);
            }
        }

        return $findings;
    }
}
