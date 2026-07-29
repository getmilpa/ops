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
 * Reads the `{id}.manifest.json` files a {@see BackupManager} run leaves behind in a
 * backup directory, and prunes the oldest ones. Stateless and filesystem-only — the
 * manifest files themselves are the catalog; there is no separate index to keep in sync.
 */
final class BackupCatalog
{
    public function __construct(private readonly string $backupDir)
    {
    }

    /**
     * All backups in `$backupDir`, newest first (descending by `createdAt`).
     *
     * @return list<BackupManifest>
     */
    public function list(): array
    {
        $manifests = [];

        foreach (glob($this->backupDir . '/*.manifest.json') ?: [] as $manifestFile) {
            /** @var array{id: string, createdAt: string, dbFile: string, storageFile: string, dbBytes: int, storageBytes: int} $data */
            $data = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
            $manifests[] = BackupManifest::fromArray($data);
        }

        usort($manifests, static fn (BackupManifest $a, BackupManifest $b): int => $b->createdAt <=> $a->createdAt);

        return $manifests;
    }

    /**
     * Delete every backup beyond the newest `$keep` (by `createdAt`), removing its
     * `.sql`/`.tar.gz`/`.manifest.json` files. `prune(0)` deletes everything.
     *
     * @return list<string> the ids of the backups that were deleted
     */
    public function prune(int $keep): array
    {
        $toRemove = array_slice($this->list(), max($keep, 0));

        $removedIds = [];
        foreach ($toRemove as $manifest) {
            foreach ([$manifest->dbFile, $manifest->storageFile, $this->manifestPath($manifest->id)] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $removedIds[] = $manifest->id;
        }

        return $removedIds;
    }

    private function manifestPath(string $id): string
    {
        return $this->backupDir . '/' . $id . '.manifest.json';
    }
}
