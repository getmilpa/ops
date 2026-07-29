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

use Milpa\Ops\Support\ReportInterface;

/**
 * The outcome of one {@see BackupManager::run()} call: the {@see BackupManifest} it
 * produced (or attempted to), whether the run succeeded, and — when it did not — why.
 * `hasFindings()` mirrors `!$ok`, so a host command exits non-zero on a failed backup
 * the same way it would on a secret or a permission violation ({@see ReportInterface}).
 */
final class BackupReport implements ReportInterface
{
    public function __construct(
        public readonly BackupManifest $manifest,
        public readonly bool $ok,
        public readonly ?string $error = null,
    ) {
    }

    /** True cuando el respaldo NO se completó — es lo único que exige acción de quien lo lea. */
    public function hasFindings(): bool
    {
        return !$this->ok;
    }

    /**
     * El respaldo como dato: el manifiesto, si salió bien, y el error si lo hubo.
     *
     * @return array{
     *     manifest: array{id: string, createdAt: string, dbFile: string, storageFile: string, dbBytes: int, storageBytes: int},
     *     ok: bool,
     *     error: ?string,
     * }
     */
    public function toArray(): array
    {
        return [
            'manifest' => $this->manifest->toArray(),
            'ok' => $this->ok,
            'error' => $this->error,
        ];
    }
}
