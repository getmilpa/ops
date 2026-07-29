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

use Milpa\Ops\Security\AdvisoryFinding;
use Milpa\Ops\Security\PermissionFinding;
use Milpa\Ops\Security\SecurityReport;
use PHPUnit\Framework\TestCase;

final class SecurityReportTest extends TestCase
{
    public function testAdvisoriesAloneMakeTheReportHaveFindings(): void
    {
        $advisory = new AdvisoryFinding(
            package: 'composer/composer',
            severity: 'high',
            title: 'Arbitrary code execution through malicious plugin',
            cve: 'CVE-2026-1',
            advisoryId: 'PKSA-composer-composer-001',
        );

        $report = new SecurityReport([], [$advisory]);

        $this->assertTrue($report->hasFindings());
    }

    public function testEmptyReportHasNoFindings(): void
    {
        $report = new SecurityReport([]);

        $this->assertFalse($report->hasFindings());
    }

    public function testToArrayCarriesAdvisories(): void
    {
        $advisory = new AdvisoryFinding(
            package: 'composer/composer',
            severity: 'high',
            title: 'Arbitrary code execution through malicious plugin',
            cve: 'CVE-2026-1',
            advisoryId: 'PKSA-composer-composer-001',
        );

        $report = new SecurityReport([], [$advisory]);
        $array = $report->toArray();

        $this->assertCount(1, $array['advisories']);

        $advisoryArray = $array['advisories'][0];
        $this->assertSame('composer/composer', $advisoryArray['package']);
        $this->assertSame('high', $advisoryArray['severity']);
        $this->assertSame('Arbitrary code execution through malicious plugin', $advisoryArray['title']);
        $this->assertSame('CVE-2026-1', $advisoryArray['cve']);
        $this->assertSame('PKSA-composer-composer-001', $advisoryArray['advisory']);
    }

    public function testPermissionsAloneMakeTheReportHaveFindings(): void
    {
        $permission = new PermissionFinding(
            path: '/tmp/.env',
            actualMode: 0o666,
            maxMode: 0o644,
            severity: 'high',
            label: 'test',
        );

        $report = new SecurityReport([], [], [$permission]);

        $this->assertTrue($report->hasFindings());
    }

    public function testToArrayCarriesPermissionsWithOctalMode(): void
    {
        $permission = new PermissionFinding(
            path: '/tmp/.env',
            actualMode: 0o666,
            maxMode: 0o644,
            severity: 'high',
            label: 'test',
        );

        $report = new SecurityReport([], [], [$permission]);
        $array = $report->toArray();

        $this->assertCount(1, $array['permissions']);

        $permissionArray = $array['permissions'][0];
        $this->assertSame('/tmp/.env', $permissionArray['path']);
        $this->assertSame('0666', $permissionArray['actual_mode']);
        $this->assertSame('0644', $permissionArray['max_mode']);
        $this->assertSame('high', $permissionArray['severity']);
        $this->assertSame('test', $permissionArray['label']);
    }
}
