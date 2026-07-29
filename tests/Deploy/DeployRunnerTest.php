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

namespace Milpa\Ops\Tests\Deploy;

use Milpa\Ops\Deploy\DeployRunner;
use Milpa\Ops\Deploy\DeployStepInterface;
use Milpa\Ops\Deploy\StepResult;
use PHPUnit\Framework\TestCase;

final class DeployRunnerTest extends TestCase
{
    private function step(string $name, ?\Throwable $throws = null): DeployStepInterface
    {
        return new class ($name, $throws) implements DeployStepInterface {
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

    public function testRunsAllStepsInOrderWhenNoneFails(): void
    {
        $report = (new DeployRunner([$this->step('build'), $this->step('up')]))->run();

        self::assertFalse($report->hasFindings());
        self::assertSame(['build', 'up'], array_map(fn (StepResult $r) => $r->name, $report->results()));
    }

    public function testFailFastStopsAtTheFirstFailingStep(): void
    {
        $report = (new DeployRunner([
            $this->step('build'),
            $this->step('up', new \RuntimeException('compose caído')),
            $this->step('health'), // must NOT run
        ]))->run();

        self::assertTrue($report->hasFindings());
        $results = $report->results();
        self::assertCount(2, $results, 'health is never reached after up fails');
        self::assertSame('build', $results[0]->name);
        self::assertTrue($results[0]->ok);
        self::assertSame('up', $results[1]->name);
        self::assertFalse($results[1]->ok);
        self::assertSame('compose caído', $results[1]->error);
    }

    public function testToArrayIsJsonShaped(): void
    {
        $okReport = (new DeployRunner([$this->step('build')]))->run();
        $arr = $okReport->toArray();
        self::assertArrayHasKey('steps', $arr);
        self::assertSame(['step', 'ok', 'error'], array_keys($arr['steps'][0]));
        self::assertSame('build', $arr['steps'][0]['step']);
        self::assertTrue($arr['steps'][0]['ok']);
        self::assertNull($arr['steps'][0]['error']);

        $failReport = (new DeployRunner([$this->step('build', new \RuntimeException('x'))]))->run();
        $failArr = $failReport->toArray();
        self::assertSame(['step', 'ok', 'error'], array_keys($failArr['steps'][0]));
        self::assertSame('x', $failArr['steps'][0]['error']);
    }
}
