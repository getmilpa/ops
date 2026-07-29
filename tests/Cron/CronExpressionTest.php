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
use InvalidArgumentException;
use Milpa\Ops\Cron\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CronExpressionTest extends TestCase
{
    public function testWildcardIsAlwaysDue(): void
    {
        $e = new CronExpression('* * * * *');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 00:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-12-31 23:59:00')));
    }

    public function testStepMinutes(): void
    {
        $e = new CronExpression('*/5 * * * *');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 10:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 10:05:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 10:10:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-21 10:03:00')));
    }

    public function testDayOfWeekHourAndMinuteMustAllMatchExactly(): void
    {
        // 2026-07-20 is a Monday.
        $e = new CronExpression('0 9 * * 1');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-20 09:00:00')));
        // Wrong minute.
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-20 09:01:00')));
        // Wrong hour.
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-20 10:00:00')));
        // Right hour/minute, wrong day-of-week (Tuesday).
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-21 09:00:00')));
    }

    public function testListMatchesAnyOfItsValues(): void
    {
        $e = new CronExpression('0,30 * * * *');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 10:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 10:30:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-21 10:15:00')));
    }

    public function testRangeMatchesEveryValueInsideIt(): void
    {
        $e = new CronExpression('0 9-17 * * *');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 09:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-21 17:00:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-21 08:00:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-21 18:00:00')));
    }

    public function testInvalidExpressionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression('* * * *');
    }

    public function testInvalidExpressionWithTooManyFieldsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression('* * * * * *');
    }

    public function testMalformedFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression('abc * * * *');
    }

    public function testDayOfWeekSevenIsAliasForSunday(): void
    {
        $e = new CronExpression('0 9 * * 7');

        // 2026-07-19 is a Sunday.
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-19 09:00:00')));
        // A non-Sunday at the same time is not due.
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-20 09:00:00')));
    }

    public function testDayOfWeekRangeWrapsThroughTheSundayAlias(): void
    {
        $e = new CronExpression('* * * * 6-7');

        // 2026-07-18 is a Saturday, 2026-07-19 is a Sunday — both are due.
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-18 12:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-19 12:00:00')));
        // 2026-07-20 is a Monday — not due.
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-20 12:00:00')));
    }

    public function testDayOfWeekFridayToSundayRangeStillWrapsAfterTheGuardFix(): void
    {
        // Regression guard: the fix that rejects inverted ranges (start > end) must NOT
        // reject this one — "5-7" is ascending on its RAW endpoints (5 <= 7); the wrap to
        // {5, 6, 0} only happens during matching, once 7 normalizes to 0.
        $e = new CronExpression('* * * * 5-7');

        // 2026-07-17 Friday, 2026-07-18 Saturday, 2026-07-19 Sunday — all due.
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-17 12:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-18 12:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-07-19 12:00:00')));
        // 2026-07-20 is a Monday — not due.
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-07-20 12:00:00')));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function outOfRangeExpressionsProvider(): iterable
    {
        yield 'minute above 59' => ['99 * * * *'];
        yield 'hour above 23' => ['0 25 * * *'];
        yield 'day-of-month above 31' => ['0 0 32 * *'];
        yield 'month above 12' => ['0 0 * 13 *'];
        yield 'day-of-week above 7' => ['* * * * 8'];
    }

    #[DataProvider('outOfRangeExpressionsProvider')]
    public function testOutOfRangeLiteralValueThrows(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression($expression);
    }

    public function testOutOfRangeRangeEndpointThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression('50-70 * * * *');
    }

    public function testStepOnANonZeroMinimumField(): void
    {
        // Odd months: 1, 3, 5, 7, 9, 11.
        $e = new CronExpression('* * * */2 *');

        self::assertTrue($e->isDue(new DateTimeImmutable('2026-01-15 00:00:00')));
        self::assertTrue($e->isDue(new DateTimeImmutable('2026-03-15 00:00:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-02-15 00:00:00')));
        self::assertFalse($e->isDue(new DateTimeImmutable('2026-04-15 00:00:00')));
    }

    /**
     * Regression guard for a bug caught in review: the day-of-week wrap-around branch
     * (needed so "5-7" can match {5, 6, 0} once 7 normalizes to 0) was gated only by
     * `start > end`, not by field — so an inverted range on ANY field silently became a
     * "wrap" instead of an error. E.g. "5-1 * * * *" built without throwing and matched
     * {0, 1, 5, 6, 7, ..., 59} (over-firing every minute except 2-4), instead of failing
     * fast at construction. Every field below must now throw on an inverted range.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function invertedRangeExpressionsProvider(): iterable
    {
        yield 'minute 5-1' => ['5-1 * * * *'];
        yield 'hour 20-5' => ['0 20-5 * * *'];
        yield 'day-of-month 25-3' => ['0 0 25-3 * *'];
        yield 'month 11-2' => ['0 0 * 11-2 *'];
        // Day-of-week: 5-1 does not involve the 7=Sunday alias at all, so there is no
        // legitimate wrap to infer — YAGNI keeps this an error rather than inventing a
        // second wrap-around rule.
        yield 'day-of-week 5-1' => ['* * * * 5-1'];
    }

    #[DataProvider('invertedRangeExpressionsProvider')]
    public function testInvertedRangeThrowsInsteadOfSilentlyWrapping(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression($expression);
    }
}
