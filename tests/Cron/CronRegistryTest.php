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

namespace Milpa\Ops\Tests\Cron;

use DateTimeImmutable;
use Milpa\Ops\Cron\CronRegistry;
use Milpa\Ops\Cron\TaskDefinition;
use PHPUnit\Framework\TestCase;

final class CronRegistryTest extends TestCase
{
    public function testAllReturnsRegisteredTasksInRegistrationOrder(): void
    {
        $registry = new CronRegistry();
        $everyMinute = new TaskDefinition('every-minute', '* * * * *', static function (): void {
        });
        $daily = new TaskDefinition('daily', '0 3 * * *', static function (): void {
        });

        $registry->register($everyMinute);
        $registry->register($daily);

        self::assertSame([$everyMinute, $daily], $registry->all());
    }

    public function testDueReturnsOnlyTasksMatchingNow(): void
    {
        $registry = new CronRegistry();
        $everyMinute = new TaskDefinition('every-minute', '* * * * *', static function (): void {
        });
        // 2026-07-21 10:00:00 is not 3am, so this task is never due at $now below.
        $daily3am = new TaskDefinition('daily-3am', '0 3 * * *', static function (): void {
        });

        $registry->register($everyMinute);
        $registry->register($daily3am);

        $due = $registry->due(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertSame([$everyMinute], $due);
    }

    public function testDueReturnsEmptyListWhenNothingIsRegistered(): void
    {
        $registry = new CronRegistry();

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->due(new DateTimeImmutable('2026-07-21 10:00:00')));
    }

    public function testDescriptionDefaultsToEmptyString(): void
    {
        $task = new TaskDefinition('no-description', '* * * * *', static function (): void {
        });

        self::assertSame('', $task->description);
        self::assertSame('* * * * *', $task->expression());
    }
}
