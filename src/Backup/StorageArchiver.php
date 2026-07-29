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

use FilesystemIterator;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Packs a fixed list of filesystem paths (files and/or directories) into a single
 * tar.gz archive via {@see PharData} — no shell-out, no `tar` binary dependency.
 * Paths that no longer exist by the time {@see self::archive()} runs are skipped
 * silently (storage directories come and go; a missing one isn't a backup failure).
 *
 * Archive mechanism decision (PharData vs ZipArchive): the well-known caveat is that
 * `PharData::compress()`/write operations require `phar.readonly=0`, since that ini
 * setting is `PHP_INI_PERDIR` and can't be flipped at runtime via `ini_set()`. This
 * package targets that caveat by testing directly against the ini value rather than
 * trusting it blindly: `phar.readonly` gates the *native* `.phar` executable-archive
 * format, not the tar/zip container format `PharData` writes here. Verified empirically
 * on this host (PHP 8.3.32): `ini_get('phar.readonly')` reports `"1"` (On) yet
 * `PharData::addFile()`/`compress(Phar::GZ)` still write successfully. We therefore use
 * `PharData` unconditionally and do NOT gate on `phar.readonly` (a static ini check would
 * have produced a false negative here). Any failure to write (e.g. `ext-phar` missing or
 * genuinely blocked) surfaces as a {@see RuntimeException} from {@see self::archive()}
 * rather than being silently swallowed — if that happens in some other environment, the
 * fix is `phar.readonly = Off` in php.ini, or a future `ZipArchive` fallback.
 */
final class StorageArchiver
{
    /** @param list<string> $paths */
    public function __construct(private readonly array $paths)
    {
    }

    /**
     * Build `$destinationTarGz` from the constructor's paths that still exist.
     * Directories are added recursively, preserving their relative file layout.
     * An all-missing path list still produces a valid, readable (empty) archive.
     */
    public function archive(string $destinationTarGz): void
    {
        if (!str_ends_with($destinationTarGz, '.gz')) {
            throw new RuntimeException(sprintf('StorageArchiver destination must end in ".gz", got "%s".', $destinationTarGz));
        }

        $tarFile = substr($destinationTarGz, 0, -3);
        $gzFile = $tarFile . '.gz';

        foreach ([$tarFile, $gzFile, $destinationTarGz] as $stale) {
            if (is_file($stale)) {
                unlink($stale);
            }
        }

        try {
            $archive = new PharData($tarFile);

            foreach ($this->paths as $path) {
                $this->addPath($archive, $path);
            }

            if (count($archive) === 0) {
                // PharData writes nothing to disk for a fully empty archive — add a
                // placeholder entry so an all-missing path list still yields a valid,
                // openable tar.gz instead of silently producing no file at all.
                $archive->addFromString('.milpa-empty', '');
            }

            $archive->compress(Phar::GZ);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to create storage archive at "%s": %s', $destinationTarGz, $e->getMessage()), 0, $e);
        } finally {
            if (is_file($tarFile)) {
                unlink($tarFile);
            }
        }

        if (!is_file($gzFile)) {
            throw new RuntimeException(sprintf('Storage archive was not written at "%s".', $destinationTarGz));
        }

        if ($gzFile !== $destinationTarGz) {
            rename($gzFile, $destinationTarGz);
        }
    }

    private function addPath(PharData $archive, string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $entryRoot = ltrim($path, '/');

        if (is_file($path)) {
            $archive->addFile($path, $entryRoot);

            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($path, '/')) + 1);
            $archive->addFile($file->getPathname(), $entryRoot . '/' . $relative);
        }
    }
}
