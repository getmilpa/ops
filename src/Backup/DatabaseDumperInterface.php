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

namespace Milpa\Ops\Backup;

/**
 * Contract for dumping the host's database to a single file. This package stays
 * domain-blind and ships no implementation — the host provides the concrete dumper
 * (e.g. shelling out to `mysqldump`, copying a SQLite file, etc). {@see BackupManager}
 * (Task 7) orchestrates a dumper alongside {@see StorageArchiver} to build a backup.
 */
interface DatabaseDumperInterface
{
    /**
     * Dump the database to `$destinationFile`. Implementations own how the dump is
     * produced (shelling out, native driver, file copy) — this contract only fixes
     * where the result lands.
     */
    public function dump(string $destinationFile): void;

    /**
     * The file extension (without the leading dot) this dumper produces, e.g. `sql`
     * for a `mysqldump` text dump or `sqlite` for a raw SQLite file copy. Callers use
     * this to name the destination file before calling {@see self::dump()}.
     */
    public function extension(): string;

    /**
     * Restore the database from `$sourceFile` (a file previously produced by
     * {@see self::dump()}). Implementations own how the restore is performed
     * (shelling out, native driver, file copy) — this contract only fixes where
     * the source data comes from. {@see BackupManager::restore()} (Task 7) calls
     * this after confirming the backup's manifest and files exist.
     */
    public function restore(string $sourceFile): void;
}
