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

use PharData;
use RuntimeException;
use Throwable;

/**
 * Orchestrates one backup "run": a {@see DatabaseDumperInterface} dump plus a
 * {@see StorageArchiver} archive, recorded as a `{id}.manifest.json` file in
 * `$backupDir` alongside the `.sql`/`.tar.gz` it produced. {@see BackupCatalog}
 * reads those manifests back; this class is the only writer of new ones.
 *
 * `run()` never throws: a failed dump or archive is caught and reported as a
 * non-`ok` {@see BackupReport} instead, so a scheduled/cron caller always gets a
 * report to act on rather than an uncaught exception mid-run.
 */
final class BackupManager
{
    public function __construct(
        private readonly string $backupDir,
        private readonly DatabaseDumperInterface $dumper,
        private readonly StorageArchiver $archiver,
    ) {
    }

    /**
     * Dump the database, archive storage, and write the manifest — all under `$id`.
     * Always returns a {@see BackupReport}; a failure mid-run is captured in it
     * (`ok: false`, `error` set) rather than propagated as an exception.
     */
    public function run(string $id, string $createdAtIso): BackupReport
    {
        $dbFile = $this->backupDir . '/' . $id . '.' . $this->dumper->extension();
        $storageFile = $this->backupDir . '/' . $id . '.tar.gz';

        try {
            $this->dumper->dump($dbFile);
            $this->archiver->archive($storageFile);

            $manifest = new BackupManifest(
                $id,
                $createdAtIso,
                $dbFile,
                $storageFile,
                is_file($dbFile) ? (int) filesize($dbFile) : 0,
                is_file($storageFile) ? (int) filesize($storageFile) : 0,
            );

            file_put_contents(
                $this->manifestPath($id),
                (string) json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            );

            return new BackupReport($manifest, true, null);
        } catch (Throwable $e) {
            // A partial dbFile/storageFile may exist on disk from the failed attempt;
            // the manifest below records the intended paths, not written sizes, since
            // no manifest file is persisted for a failed run.
            $manifest = new BackupManifest($id, $createdAtIso, $dbFile, $storageFile, 0, 0);

            return new BackupReport($manifest, false, $e->getMessage());
        }
    }

    /**
     * Restore the database and storage from a previously completed backup. Confirming
     * this destructive action with the operator is the caller's job (the command),
     * not this method's — by the time `restore()` runs, it executes unconditionally.
     *
     * @throws RuntimeException if `$id` has no manifest, or its database dump is missing
     */
    public function restore(string $id): void
    {
        $manifestFile = $this->manifestPath($id);

        if (!is_file($manifestFile)) {
            throw new RuntimeException(sprintf('No backup found with id "%s" (missing manifest at "%s").', $id, $manifestFile));
        }

        /** @var array{id: string, createdAt: string, dbFile: string, storageFile: string, dbBytes: int, storageBytes: int} $data */
        $data = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        $manifest = BackupManifest::fromArray($data);

        if (!is_file($manifest->dbFile)) {
            throw new RuntimeException(sprintf('Backup "%s" is missing its database dump at "%s".', $id, $manifest->dbFile));
        }

        $this->dumper->restore($manifest->dbFile);

        if (is_file($manifest->storageFile)) {
            $this->restoreStorageArchive($manifest->storageFile);
        }
    }

    /**
     * {@see StorageArchiver::archive()} names each tar entry after the archived path's
     * own absolute location (leading `/` stripped). Extracting to `/` is therefore the
     * exact inverse: every member lands back at the absolute path it was archived from.
     *
     * SECURITY (tracked, Task 8): this extracts to the filesystem root using the archive's
     * embedded absolute entry paths — there is NO path-containment check here. A .tar.gz that
     * was tampered with in the backup directory between backup and restore could plant entries
     * outside the intended storage roots (write-what-where). The trust boundary on restore is
     * WEAKER than on archive (which reads a live, config-provided path list): restore trusts
     * on-disk archive contents. The host restore command (Task 8) MUST validate that every
     * extracted entry stays within the manifest's recorded storage roots before extraction,
     * and gate the whole operation behind explicit admin confirmation.
     */
    private function restoreStorageArchive(string $archiveFile): void
    {
        try {
            $archive = new PharData($archiveFile);
            $archive->extractTo('/', null, true);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to restore storage archive "%s": %s', $archiveFile, $e->getMessage()), 0, $e);
        }
    }

    private function manifestPath(string $id): string
    {
        return $this->backupDir . '/' . $id . '.manifest.json';
    }
}
