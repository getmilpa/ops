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
use Milpa\Ops\Cron\CronRunner;
use Milpa\Ops\Cron\TaskDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class CronRunnerTest extends TestCase
{
    public function testRunExecutesDueTasksAndIsolatesAThrowingOneFromTheOthers(): void
    {
        $registry = new CronRegistry();
        $ranOk = false;

        $registry->register(new TaskDefinition('ok-task', '* * * * *', static function () use (&$ranOk): void {
            $ranOk = true;
        }));
        $registry->register(new TaskDefinition('throwing-task', '* * * * *', static function (): void {
            throw new RuntimeException('boom');
        }));
        // Not due at 10:00:00 (only 3am is) — must be absent from results entirely, and its
        // callback must never run.
        $registry->register(new TaskDefinition('daily-3am', '0 3 * * *', static function (): void {
            throw new RuntimeException('must never run');
        }));

        $runner = new CronRunner($registry);
        $report = $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertTrue($ranOk);
        self::assertCount(2, $report->results);

        $byName = [];
        foreach ($report->results as $result) {
            $byName[$result->name] = $result;
        }

        self::assertArrayHasKey('ok-task', $byName);
        self::assertArrayHasKey('throwing-task', $byName);
        self::assertArrayNotHasKey('daily-3am', $byName);

        self::assertTrue($byName['ok-task']->ok);
        self::assertNull($byName['ok-task']->error);

        self::assertFalse($byName['throwing-task']->ok);
        self::assertSame('boom', $byName['throwing-task']->error);

        self::assertTrue($report->hasFindings());
    }

    public function testRunTimesEachTaskInSeconds(): void
    {
        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('sleepy', '* * * * *', static function (): void {
            usleep(2000);
        }));

        $runner = new CronRunner($registry);
        $report = $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertCount(1, $report->results);
        self::assertGreaterThan(0.0, $report->results[0]->seconds);
    }

    public function testRunReturnsAnEmptyReportWithNoFindingsWhenNothingIsDue(): void
    {
        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('daily-3am', '0 3 * * *', static function (): void {
        }));

        $runner = new CronRunner($registry);
        $report = $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertSame([], $report->results);
        self::assertFalse($report->hasFindings());
    }

    public function testLoggerReceivesAnErrorOnlyForTheFailingTask(): void
    {
        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('ok-task', '* * * * *', static function (): void {
        }));
        $registry->register(new TaskDefinition('throwing-task', '* * * * *', static function (): void {
            throw new RuntimeException('boom');
        }));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('throwing-task'), self::isType('array'));

        $runner = new CronRunner($registry, $logger);
        $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));
    }

    public function testRunWorksWithoutALoggerWired(): void
    {
        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('throwing-task', '* * * * *', static function (): void {
            throw new RuntimeException('boom');
        }));

        $runner = new CronRunner($registry);
        $report = $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertFalse($report->results[0]->ok);
    }

    public function testToArrayCarriesEachResult(): void
    {
        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('ok-task', '* * * * *', static function (): void {
        }));

        $runner = new CronRunner($registry);
        $report = $runner->run(new DateTimeImmutable('2026-07-21 10:00:00'));
        $array = $report->toArray();

        self::assertCount(1, $array['results']);
        self::assertSame('ok-task', $array['results'][0]['name']);
        self::assertTrue($array['results'][0]['ok']);
        self::assertArrayHasKey('seconds', $array['results'][0]);
        self::assertArrayHasKey('error', $array['results'][0]);
    }

    /**
     * A task that throws does not stop the ones AFTER it — the promise this runner's docblock makes and
     * that nothing measured until now.
     *
     * Every existing test that registers a thrower registers it LAST among the due tasks, so a runner
     * that stopped dead on the first failure passed all of them. Measured: mutating `run()` to
     * `if (!$r->ok) { break; }` left 33 of 33 green. A promise no test can falsify is a comment.
     *
     * The order is the whole test: the thrower goes FIRST.
     */
    public function testATaskThatThrowsDoesNotStopTheOnesAfterIt(): void
    {
        $ranAfter = false;

        $registry = new CronRegistry();
        $registry->register(new TaskDefinition('throwing-task', '* * * * *', static function (): void {
            throw new RuntimeException('boom');
        }));
        $registry->register(new TaskDefinition('later-task', '* * * * *', static function () use (&$ranAfter): void {
            $ranAfter = true;
        }));

        $report = (new CronRunner($registry))->run(new DateTimeImmutable('2026-07-21 10:00:00'));

        self::assertTrue($ranAfter, 'the task registered after the thrower never ran — the runner stopped');
        self::assertCount(2, $report->results, 'both tasks are reported, the failure included');
        self::assertFalse($report->results[0]->ok, 'the thrower is reported as failed');
        self::assertTrue($report->results[1]->ok, 'and the one after it as run');
    }
}
