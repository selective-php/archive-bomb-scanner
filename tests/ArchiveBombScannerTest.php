<?php

namespace Selective\ArchiveBomb\Test;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selective\ArchiveBomb\Engine\PngBompEngine;
use Selective\ArchiveBomb\Engine\RarBombEngine;
use Selective\ArchiveBomb\Engine\ZipBombEngine;
use Selective\ArchiveBomb\Scanner\BombScanner;
use Selective\ArchiveBomb\Scanner\BombScannerResult;
use SplFileObject;
use SplTempFileObject;

/**
 * Test.
 */
class ArchiveBombScannerTest extends TestCase
{
    /**
     * Test.
     */
    public function testCreateInstance(): void
    {
        $this->assertInstanceOf(BombScanner::class, new BombScanner());
    }

    /**
     * Test.
     *
     * @dataProvider providerGetImageTypeFromFile
     *
     * @param string $filename The file
     * @param bool $expected The expected result
     */
    public function testScanFile(string $filename, bool $expected): void
    {
        $this->assertFileExists($filename);

        $scanner = new BombScanner();

        $scanner->addEngine(new ZipBombEngine());
        $scanner->addEngine(new PngBompEngine());
        $scanner->addEngine(new RarBombEngine());

        $file = new SplFileObject($filename);
        $actual = $scanner->scanFile($file);

        $this->assertSame($expected, $actual->isBomb());
        $this->assertTrue($actual->equals(new BombScannerResult($actual->isBomb())));

        // In memory scanning
        $tempFile = new SplTempFileObject();
        $tempFile->fwrite((string)file_get_contents($filename));
        $actual = $scanner->scanFile($file);

        $this->assertSame($expected, $actual->isBomb());
    }

    /**
     * Provider.
     *
     * @return array<mixed> The test data
     */
    public function providerGetImageTypeFromFile(): array
    {
        $result = [];

        foreach ($this->findFiles(__DIR__ . '/files/zip') as $file) {
            $result[] = [$file, true];
        }

        foreach ($this->findFiles(__DIR__ . '/files/zip-ok') as $file) {
            $result[] = [$file, false];
        }

        foreach ($this->findFiles(__DIR__ . '/files/rar') as $file) {
            $result[] = [$file, true];
        }

        foreach ($this->findFiles(__DIR__ . '/files/rar-ok') as $file) {
            $result[] = [$file, false];
        }

        foreach ($this->findFiles(__DIR__ . '/files/png') as $file) {
            $result[] = [$file, true];
        }

        foreach ($this->findFiles(__DIR__ . '/files/png-ok') as $file) {
            $result[] = [$file, false];
        }

        return $result;
    }

    /**
     * Find all files.
     *
     * @param string $path The path
     *
     * @return array<int, string> The files
     */
    private function findFiles(string $path): array
    {
        $result = [];
        $files = glob(sprintf('%s/*', $path));

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $result[] = (string)realpath($file);
        }

        return $result;
    }

    /**
     * Test.
     */
    public function testScanFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: temp.zip');

        $scanner = new BombScanner();
        $scanner->addEngine(new ZipBombEngine());

        $filename = __DIR__ . '/temp.zip';
        touch($filename);

        $file = new SplFileObject($filename);

        unlink($filename);

        $scanner->scanFile($file);
    }
}
