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

/**
 * One permission policy checked by {@see PermissionChecker}: a file path and the maximum octal
 * mode it may have. `maxMode` is a ceiling, not an exact match — any mode at or under it (e.g.
 * a stricter 0600 against a 0644 ceiling) passes. The host assembles rules from its own config
 * (Task 5); this package stays domain-blind to where the policy comes from.
 */
final readonly class PermissionRule
{
    public function __construct(
        public string $path,
        public int $maxMode,
        public string $severity,
        public string $label,
    ) {
    }
}
