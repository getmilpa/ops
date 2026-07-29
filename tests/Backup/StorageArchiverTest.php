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
use Milpa\Ops\Backup\StorageArchiver;
use PharData;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class StorageArchiverTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/ops-backup-' . bin2hex(random_bytes(4));
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmp);
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

    private function write(string $name, string $content): string
    {
        $p = $this->tmp . '/' . $name;
        file_put_contents($p, $content);

        return $p;
    }

    public function testArchivesTheExistingPathsAndIgnoresAMissingOne(): void
    {
        $fileA = $this->write('a.txt', 'hello');
        $fileB = $this->write('b.txt', 'world');
        $missing = $this->tmp . '/does-not-exist.txt';
        $destination = $this->tmp . '/out.tar.gz';

        $archiver = new StorageArchiver([$fileA, $fileB, $missing]);
        $archiver->archive($destination);

        self::assertFileExists($destination);

        $members = [];
        $archive = new PharData($destination);
        foreach (new RecursiveIteratorIterator($archive) as $entry) {
            $members[] = $entry->getFilename();
        }
        sort($members);

        self::assertSame(['a.txt', 'b.txt'], $members);
    }

    public function testArchivingOnlyMissingPathsStillProducesAReadableArchive(): void
    {
        $missing = $this->tmp . '/nope.txt';
        $destination = $this->tmp . '/empty.tar.gz';

        $archiver = new StorageArchiver([$missing]);
        $archiver->archive($destination);

        self::assertFileExists($destination);

        // Must be openable/readable — an empty tar.gz is still a valid archive.
        $archive = new PharData($destination);
        self::assertInstanceOf(PharData::class, $archive);
    }

    public function testArchivingADirectoryWalksNestedSubdirectoriesPreservingRelativePaths(): void
    {
        $dir = $this->tmp . '/mydir';
        mkdir($dir);
        mkdir($dir . '/sub');
        mkdir($dir . '/sub/nested');
        file_put_contents($dir . '/sub/one.txt', 'one');
        file_put_contents($dir . '/sub/nested/two.txt', 'two');
        $destination = $this->tmp . '/nested.tar.gz';

        $archiver = new StorageArchiver([$dir]);
        $archiver->archive($destination);

        self::assertFileExists($destination);

        $archive = new PharData($destination);
        $entryRootPrefix = 'phar://' . $archive->getPath() . '/' . ltrim($dir, '/') . '/';

        $relativeMembers = [];
        foreach (new RecursiveIteratorIterator($archive) as $entry) {
            self::assertStringStartsWith($entryRootPrefix, $entry->getPathname());
            $relativeMembers[] = substr($entry->getPathname(), strlen($entryRootPrefix));
        }
        sort($relativeMembers);

        self::assertSame(['sub/nested/two.txt', 'sub/one.txt'], $relativeMembers);
    }
}
