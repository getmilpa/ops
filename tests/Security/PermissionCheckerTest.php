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

use Milpa\Ops\Security\PermissionChecker;
use Milpa\Ops\Security\PermissionRule;
use PHPUnit\Framework\TestCase;

final class PermissionCheckerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/ops-perm-' . bin2hex(random_bytes(4));
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        // glob('*') skips dotfiles (this suite writes a literal `.env`), so list the
        // directory directly rather than leaking tmp dirs across test runs.
        foreach ((scandir($this->tmp) ?: []) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->tmp . '/' . $entry);
            }
        }
        rmdir($this->tmp);
    }

    public function testFlagsAFileMorePermissiveThanAllowed(): void
    {
        $f = $this->tmp . '/.env';
        file_put_contents($f, 'SECRET=1');
        chmod($f, 0666);

        $findings = (new PermissionChecker())->check([
            new PermissionRule($f, 0644, 'high', '.env no debe ser world-writable'),
        ]);

        self::assertCount(1, $findings);
        self::assertSame($f, $findings[0]->path);
    }

    public function testStricterThanAllowedIsFine(): void
    {
        $f = $this->tmp . '/.env';
        file_put_contents($f, 'SECRET=1');
        chmod($f, 0600);
        self::assertSame([], (new PermissionChecker())->check([new PermissionRule($f, 0644, 'high', 'x')]));
    }

    public function testMissingFileIsIgnored(): void
    {
        self::assertSame([], (new PermissionChecker())->check([new PermissionRule($this->tmp . '/nope', 0644, 'high', 'x')]));
    }
}
