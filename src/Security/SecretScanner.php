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

use InvalidArgumentException;

/**
 * Scans a set of files line by line for secrets matching regex rules.
 *
 * Lines containing an allowlisted substring are skipped entirely. Every
 * matching line is reported as a {@see SecretFinding} whose excerpt is
 * redacted — the full secret is never echoed back into the report.
 */
final class SecretScanner
{
    /**
     * @param list<array{id: string, pattern: string, severity: string}> $rules
     * @param list<string>                                               $allowlist substrings that, if present in a line, suppress any match on it
     *
     * @throws InvalidArgumentException when a rule's pattern is not a valid PCRE
     */
    public function __construct(
        private readonly array $rules,
        private readonly array $allowlist = [],
    ) {
        foreach ($this->rules as $rule) {
            if (@preg_match($rule['pattern'], '') === false) {
                throw new InvalidArgumentException("rule '{$rule['id']}': invalid pattern");
            }
        }
    }

    /**
     * Recorre los archivos línea por línea y devuelve lo que casó con alguna regla, salvo lo que la
     * lista de excepciones perdona.
     *
     * Un archivo ilegible se salta en silencio: un escáner que aborta a la mitad reporta menos
     * hallazgos y se ve exactamente igual que uno que no encontró nada.
     *
     * @param iterable<string> $files absolute paths
     */
    public function scan(iterable $files): SecurityReport
    {
        $findings = [];
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $i => $line) {
                if ($this->allowlisted($line)) {
                    continue;
                }

                foreach ($this->rules as $rule) {
                    if (preg_match($rule['pattern'], $line, $matches, PREG_OFFSET_CAPTURE) === 1) {
                        [$match, $offset] = $matches[0];

                        $findings[] = new SecretFinding(
                            $rule['id'],
                            $rule['severity'],
                            $file,
                            $i + 1,
                            $this->redact($line, $match, $offset),
                        );
                    }
                }
            }
        }

        return new SecurityReport($findings);
    }

    private function allowlisted(string $line): bool
    {
        foreach ($this->allowlist as $needle) {
            if ($needle !== '' && str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact exactly the span the rule matched — not a length-based heuristic over
     * the whole line. Keeps at most the first 4 chars of the match and masks the
     * rest; a match of 4 chars or fewer is masked entirely. Either way the excerpt
     * never contains the full match, regardless of how short the secret is.
     */
    private function redact(string $line, string $match, int $offset): string
    {
        $matchLength = strlen($match);
        $keep = $matchLength > 4 ? 4 : 0;
        $masked = substr($match, 0, $keep) . str_repeat('*', $matchLength - $keep);

        return trim(substr($line, 0, $offset) . $masked . substr($line, $offset + $matchLength));
    }
}
