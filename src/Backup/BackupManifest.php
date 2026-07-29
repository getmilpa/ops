<?php

/**
 * Reconstruye el manifiesto de un respaldo ya escrito. Es la mitad que permite restaurar: sin ella, un respaldo es un archivo que nadie sabe interpretar.
 *
 * El manifiesto como dato plano, listo para escribirse junto al respaldo.
 *
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
 * A record of one completed backup: which run (`id`/`createdAt`), where the database
 * dump and storage archive landed, and their sizes. {@see BackupManager} (Task 7)
 * produces these; hosts persist/list them via `toArray()`/`fromArray()`.
 */
final readonly class BackupManifest
{
    public function __construct(
        public string $id,
        public string $createdAt,
        public string $dbFile,
        public string $storageFile,
        public int $dbBytes,
        public int $storageBytes,
    ) {
    }

    /**
     * El manifiesto como dato plano, listo para escribirse junto al respaldo.
     *
     * @return array{id: string, createdAt: string, dbFile: string, storageFile: string, dbBytes: int, storageBytes: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt,
            'dbFile' => $this->dbFile,
            'storageFile' => $this->storageFile,
            'dbBytes' => $this->dbBytes,
            'storageBytes' => $this->storageBytes,
        ];
    }

    /**
     * Reconstruye el manifiesto de un respaldo ya escrito. Es la mitad que permite restaurar: sin ella, un respaldo es un archivo que nadie sabe interpretar.
     *
     * @param array{id: string, createdAt: string, dbFile: string, storageFile: string, dbBytes: int, storageBytes: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['createdAt'],
            $data['dbFile'],
            $data['storageFile'],
            $data['dbBytes'],
            $data['storageBytes'],
        );
    }
}
