<?php

/**
 * Traduce la salida JSON de `composer audit` a hallazgos de esta casa. Aísla el formato de una herramienta ajena en un solo lugar: cuando cambie, cambia aquí y no en cada consumidor.
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
 * Parses the JSON produced by `composer audit --format=json` into {@see AdvisoryFinding} objects.
 *
 * Domain-blind by design (ADR-0023): this package never shells out to `composer` itself — the
 * host command runs the audit and hands this parser its JSON output.
 */
final class AuditParser
{
    /**
     * Traduce la salida JSON de `composer audit` a hallazgos de esta casa. Aísla el formato de una herramienta ajena en un solo lugar: cuando cambie, cambia aquí y no en cada consumidor.
     *
     * @return list<AdvisoryFinding>
     *
     * @throws \JsonException on malformed input
     */
    public function parse(string $composerAuditJson): array
    {
        /** @var array{advisories?: array<string, list<array<string, mixed>>>} $data */
        $data = json_decode($composerAuditJson, true, 512, JSON_THROW_ON_ERROR);

        $out = [];
        foreach (($data['advisories'] ?? []) as $advisories) {
            foreach ($advisories as $a) {
                $out[] = new AdvisoryFinding(
                    (string) ($a['packageName'] ?? ''),
                    (string) ($a['severity'] ?? 'unknown'),
                    (string) ($a['title'] ?? ''),
                    isset($a['cve']) && $a['cve'] !== '' ? (string) $a['cve'] : null,
                    (string) ($a['advisoryId'] ?? ''),
                );
            }
        }

        return $out;
    }
}
