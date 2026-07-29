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

use InvalidArgumentException;
use Milpa\Ops\Security\SecretScanner;
use PHPUnit\Framework\TestCase;

final class SecretScannerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/ops-sec-' . bin2hex(random_bytes(4));
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp . '/*') ?: []);
        rmdir($this->tmp);
    }

    private function write(string $name, string $content): string
    {
        $p = $this->tmp . '/' . $name;
        file_put_contents($p, $content);

        return $p;
    }

    public function testFindsAMatchingSecret(): void
    {
        $file = $this->write('config.php', "<?php\n\$key = 'AKIAIOSFODNN7EXAMPLE';\n");
        $scanner = new SecretScanner([
            ['id' => 'aws-access-key', 'pattern' => '/AKIA[0-9A-Z]{16}/', 'severity' => 'high'],
        ]);

        $report = $scanner->scan([$file]);

        self::assertTrue($report->hasFindings());
        $findings = $report->findings();
        self::assertCount(1, $findings);
        self::assertSame('aws-access-key', $findings[0]->ruleId);
        self::assertSame(2, $findings[0]->line);
        self::assertSame('high', $findings[0]->severity);
        self::assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $findings[0]->excerpt);
    }

    public function testShortSecretIsNeverLeakedInTheExcerpt(): void
    {
        $file = $this->write('config.php', "<?php\n\$k='sk_a1B2c3D4';\n");
        $scanner = new SecretScanner([
            ['id' => 'short-token', 'pattern' => '/sk_[A-Za-z0-9]{8,10}/', 'severity' => 'high'],
        ]);

        $report = $scanner->scan([$file]);
        $findings = $report->findings();

        self::assertCount(1, $findings);
        self::assertStringNotContainsString('sk_a1B2c3D4', $findings[0]->excerpt);
    }

    public function testConstructorRejectsAnInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SecretScanner([
            ['id' => 'broken', 'pattern' => 'AKIA', 'severity' => 'high'],
        ]);
    }

    public function testAllowlistSuppressesAMatch(): void
    {
        $file = $this->write('example.php', "<?php\n\$key = 'AKIAIOSFODNN7EXAMPLE'; // example placeholder\n");
        $scanner = new SecretScanner(
            [['id' => 'aws-access-key', 'pattern' => '/AKIA[0-9A-Z]{16}/', 'severity' => 'high']],
            allowlist: ['example placeholder'],
        );

        $report = $scanner->scan([$file]);

        self::assertFalse($report->hasFindings());
    }

    public function testCleanFileYieldsNoFindings(): void
    {
        $file = $this->write('clean.php', "<?php\nreturn ['ok' => true];\n");
        $scanner = new SecretScanner([
            ['id' => 'aws-access-key', 'pattern' => '/AKIA[0-9A-Z]{16}/', 'severity' => 'high'],
        ]);

        self::assertFalse($scanner->scan([$file])->hasFindings());
    }

    public function testReportToArrayIsJsonShaped(): void
    {
        $file = $this->write('c.php', "<?php\n\$k='AKIAIOSFODNN7EXAMPLE';\n");
        $report = (new SecretScanner([['id' => 'aws', 'pattern' => '/AKIA[0-9A-Z]{16}/', 'severity' => 'high']]))->scan([$file]);
        $arr = $report->toArray();

        self::assertArrayHasKey('secrets', $arr);
        self::assertSame('aws', $arr['secrets'][0]['rule']);
    }
}
