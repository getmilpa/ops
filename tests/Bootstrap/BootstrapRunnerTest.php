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

namespace Milpa\Ops\Tests\Bootstrap;

use Milpa\Ops\Bootstrap\BootstrapPhaseInterface;
use Milpa\Ops\Bootstrap\BootstrapRunner;
use Milpa\Ops\Bootstrap\PhaseResult;
use PHPUnit\Framework\TestCase;

final class BootstrapRunnerTest extends TestCase
{
    private function phase(string $name, ?\Throwable $throws = null): BootstrapPhaseInterface
    {
        return new class ($name, $throws) implements BootstrapPhaseInterface {
            public function __construct(private string $n, private ?\Throwable $t)
            {
            }
            public function name(): string
            {
                return $this->n;
            }
            public function run(): void
            {
                if ($this->t !== null) {
                    throw $this->t;
                }
            }
        };
    }

    public function testRunsAllPhasesInOrderWhenNoneFails(): void
    {
        $report = (new BootstrapRunner([$this->phase('schema'), $this->phase('rows')]))->run();

        self::assertFalse($report->hasFindings());
        self::assertSame(['schema', 'rows'], array_map(fn (PhaseResult $r) => $r->name, $report->results()));
    }

    public function testFailFastStopsAtTheFirstFailingPhase(): void
    {
        $report = (new BootstrapRunner([
            $this->phase('schema'),
            $this->phase('rows', new \RuntimeException('registro caído')),
            $this->phase('hooks'), // must NOT run
        ]))->run();

        self::assertTrue($report->hasFindings());
        $results = $report->results();
        self::assertCount(2, $results, 'hooks is never reached after rows fails');
        self::assertSame('schema', $results[0]->name);
        self::assertTrue($results[0]->ok);
        self::assertSame('rows', $results[1]->name);
        self::assertFalse($results[1]->ok);
        self::assertSame('registro caído', $results[1]->error);
    }

    public function testToArrayIsJsonShaped(): void
    {
        // Test success case: all three keys present with error = null
        $successReport = (new BootstrapRunner([$this->phase('schema')]))->run();
        $successArr = $successReport->toArray();

        self::assertArrayHasKey('phases', $successArr);
        self::assertCount(1, $successArr['phases']);
        self::assertSame(['phase', 'ok', 'error'], array_keys($successArr['phases'][0]));
        self::assertSame('schema', $successArr['phases'][0]['phase']);
        self::assertTrue($successArr['phases'][0]['ok']);
        self::assertNull($successArr['phases'][0]['error']);

        // Test failure case: all three keys present with error = non-null string
        $failureReport = (new BootstrapRunner([$this->phase('rows', new \RuntimeException('dropped row'))]))->run();
        $failureArr = $failureReport->toArray();

        self::assertArrayHasKey('phases', $failureArr);
        self::assertCount(1, $failureArr['phases']);
        self::assertSame(['phase', 'ok', 'error'], array_keys($failureArr['phases'][0]));
        self::assertSame('rows', $failureArr['phases'][0]['phase']);
        self::assertFalse($failureArr['phases'][0]['ok']);
        self::assertSame('dropped row', $failureArr['phases'][0]['error']);
    }
}
