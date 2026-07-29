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

namespace Milpa\Ops\Tests\Fixtures;

use Milpa\Ops\Backup\DatabaseDumperInterface;
use RuntimeException;

/**
 * No-DB-attached double for {@see DatabaseDumperInterface}: `dump()` writes a
 * dummy `.sql` file with recognizable content instead of shelling out to a real
 * database, and `restore()` records what it was asked to restore from — enough
 * for {@see \Milpa\Ops\Backup\BackupManager} tests to exercise a full run/restore
 * round trip without touching a real database. `$failDump` lets a test force
 * `dump()` to throw, to exercise {@see \Milpa\Ops\Backup\BackupManager::run()}'s
 * failure path.
 */
final class FakeDumper implements DatabaseDumperInterface
{
    public int $dumpCalls = 0;

    /** @var list<string> */
    public array $restoredFrom = [];

    public function __construct(private readonly bool $failDump = false)
    {
    }

    public function dump(string $destinationFile): void
    {
        ++$this->dumpCalls;

        if ($this->failDump) {
            throw new RuntimeException('fake dump failure');
        }

        file_put_contents($destinationFile, "-- fake dump\n");
    }

    public function extension(): string
    {
        return 'sql';
    }

    public function restore(string $sourceFile): void
    {
        $this->restoredFrom[] = $sourceFile;
    }
}
