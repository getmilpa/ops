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

namespace Milpa\Ops\Tests\Support;

use PHPUnit\Framework\TestCase;

final class PackageSanityTest extends TestCase
{
    public function testReportContractExists(): void
    {
        self::assertTrue(interface_exists(\Milpa\Ops\Support\ReportInterface::class));
        $r = new \ReflectionClass(\Milpa\Ops\Support\ReportInterface::class);
        self::assertTrue($r->hasMethod('toArray'));
        self::assertTrue($r->hasMethod('hasFindings'));
    }
}
