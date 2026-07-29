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

namespace Milpa\Ops\Tests\Backup;

use FilesystemIterator;
use Milpa\Ops\Backup\BackupCatalog;
use Milpa\Ops\Backup\BackupManager;
use Milpa\Ops\Backup\BackupManifest;
use Milpa\Ops\Backup\StorageArchiver;
use Milpa\Ops\Tests\Fixtures\FakeDumper;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupManagerTest extends TestCase
{
    private string $backupDir;
    private string $storageDir;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir() . '/ops-backup-mgr-' . bin2hex(random_bytes(4));
        mkdir($this->backupDir);

        $this->storageDir = sys_get_temp_dir() . '/ops-backup-storage-' . bin2hex(random_bytes(4));
        mkdir($this->storageDir);
        file_put_contents($this->storageDir . '/upload.txt', 'storage content');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->backupDir);
        $this->removeDir($this->storageDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    private function makeManager(FakeDumper $dumper): BackupManager
    {
        return new BackupManager($this->backupDir, $dumper, new StorageArchiver([$this->storageDir]));
    }

    public function testRunProducesTheSqlTarGzAndManifestFiles(): void
    {
        $manager = $this->makeManager(new FakeDumper());

        $report = $manager->run('bk1', '2026-07-21T10:00:00Z');

        self::assertTrue($report->ok);
        self::assertNull($report->error);
        self::assertFalse($report->hasFindings());

        self::assertFileExists($this->backupDir . '/bk1.sql');
        self::assertFileExists($this->backupDir . '/bk1.tar.gz');
        self::assertFileExists($this->backupDir . '/bk1.manifest.json');

        self::assertSame('bk1', $report->manifest->id);
        self::assertSame('2026-07-21T10:00:00Z', $report->manifest->createdAt);
        self::assertSame($this->backupDir . '/bk1.sql', $report->manifest->dbFile);
        self::assertSame($this->backupDir . '/bk1.tar.gz', $report->manifest->storageFile);
        self::assertGreaterThan(0, $report->manifest->dbBytes);
        self::assertGreaterThan(0, $report->manifest->storageBytes);

        $persisted = json_decode((string) file_get_contents($this->backupDir . '/bk1.manifest.json'), true);
        self::assertSame($report->manifest->toArray(), $persisted);
    }

    public function testRunReportsFailureWithoutThrowingWhenTheDumperFails(): void
    {
        $manager = $this->makeManager(new FakeDumper(failDump: true));

        $report = $manager->run('bk1', '2026-07-21T10:00:00Z');

        self::assertFalse($report->ok);
        self::assertTrue($report->hasFindings());
        self::assertSame('fake dump failure', $report->error);
        self::assertFileDoesNotExist($this->backupDir . '/bk1.manifest.json');
    }

    public function testCatalogListsBackupsDescendingByCreatedAt(): void
    {
        $manager = $this->makeManager(new FakeDumper());

        $manager->run('bk1', '2026-07-21T10:00:00Z');
        $manager->run('bk2', '2026-07-22T10:00:00Z');
        $manager->run('bk3', '2026-07-20T10:00:00Z');

        $manifests = (new BackupCatalog($this->backupDir))->list();

        self::assertSame(
            ['bk2', 'bk1', 'bk3'],
            array_map(static fn (BackupManifest $m): string => $m->id, $manifests),
        );
    }

    public function testPruneZeroDeletesEveryBackupFile(): void
    {
        $manager = $this->makeManager(new FakeDumper());
        $manager->run('bk1', '2026-07-21T10:00:00Z');

        $catalog = new BackupCatalog($this->backupDir);
        $removed = $catalog->prune(0);

        self::assertSame(['bk1'], $removed);
        self::assertFileDoesNotExist($this->backupDir . '/bk1.sql');
        self::assertFileDoesNotExist($this->backupDir . '/bk1.tar.gz');
        self::assertFileDoesNotExist($this->backupDir . '/bk1.manifest.json');
        self::assertSame([], $catalog->list());
    }

    public function testPruneKeepsOnlyTheNewestNBackups(): void
    {
        $manager = $this->makeManager(new FakeDumper());
        $manager->run('bk1', '2026-07-20T10:00:00Z');
        $manager->run('bk2', '2026-07-21T10:00:00Z');
        $manager->run('bk3', '2026-07-22T10:00:00Z');

        $catalog = new BackupCatalog($this->backupDir);
        $removed = $catalog->prune(1);
        sort($removed);

        self::assertSame(['bk1', 'bk2'], $removed);
        self::assertSame(['bk3'], array_map(static fn (BackupManifest $m): string => $m->id, $catalog->list()));
    }

    public function testRestoreCallsDumperRestoreAndRecreatesTheStorageFile(): void
    {
        $dumper = new FakeDumper();
        $manager = $this->makeManager($dumper);
        $manager->run('bk1', '2026-07-21T10:00:00Z');

        // Simulate data loss on both sides before restoring.
        unlink($this->storageDir . '/upload.txt');
        self::assertFileDoesNotExist($this->storageDir . '/upload.txt');

        $manager->restore('bk1');

        self::assertSame([$this->backupDir . '/bk1.sql'], $dumper->restoredFrom);
        self::assertFileExists($this->storageDir . '/upload.txt');
    }

    public function testRestoreThrowsWhenTheBackupIdIsUnknown(): void
    {
        $manager = $this->makeManager(new FakeDumper());

        $this->expectException(RuntimeException::class);

        $manager->restore('does-not-exist');
    }

    public function testRestoreThrowsWhenTheManifestExistsButTheDbFileIsMissing(): void
    {
        $dumper = new FakeDumper();
        $manager = $this->makeManager($dumper);
        $manager->run('bk1', '2026-07-21T10:00:00Z');

        unlink($this->backupDir . '/bk1.sql');

        $this->expectException(RuntimeException::class);

        $manager->restore('bk1');
    }
}
