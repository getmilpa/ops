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

namespace Milpa\Ops\Tests\Security;

use Milpa\Ops\Security\AuditParser;
use PHPUnit\Framework\TestCase;

final class AuditParserTest extends TestCase
{
    public function testParsesAdvisoriesFromComposerAuditJson(): void
    {
        $json = json_encode([
            'advisories' => [
                'composer/composer' => [
                    ['advisoryId' => 'PKSA-x', 'packageName' => 'composer/composer', 'severity' => 'high', 'title' => 'Path traversal', 'cve' => 'CVE-2026-59948'],
                ],
            ],
            'abandoned' => [],
            'filter' => [],
        ]);

        $advisories = (new AuditParser())->parse($json);

        self::assertCount(1, $advisories);
        self::assertSame('composer/composer', $advisories[0]->package);
        self::assertSame('high', $advisories[0]->severity);
        self::assertSame('CVE-2026-59948', $advisories[0]->cve);
    }

    public function testEmptyAuditYieldsNoAdvisories(): void
    {
        $json = json_encode(['advisories' => [], 'abandoned' => [], 'filter' => []]);
        self::assertSame([], (new AuditParser())->parse($json));
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(\JsonException::class);
        (new AuditParser())->parse('not json');
    }
}
