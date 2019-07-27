<?php

namespace Selective\ArchiveBomb\Test;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selective\ArchiveBomb\Engine\ZipBombEngine;
use Selective\ArchiveBomb\Scanner\ArchiveBombScanner;
use SplFileObject;
use SplTempFileObject;

/**
 * Test.
 */
class ArchiveBombScannerTest extends TestCase
{
    /**
     * Test.
     *
     * @return void
     */
    public function testCreateInstance(): void
    {
        new ArchiveBombScanner();

        self::assertTrue(true);
    }

    /**
     * Test.
     *
     * @dataProvider providerGetImageTypeFromFile
     *
     * @param string $filename The file
     * @param bool $expected The expected result
     *
     * @return void
     */
    public function testScanFile(string $filename, bool $expected): void
    {
        self::assertFileExists($filename);

        $scanner = new ArchiveBombScanner();

        $scanner->addEngine(new ZipBombEngine());

        $file = new SplFileObject($filename);
        $actual = $scanner->scanFile($file);

        self::assertSame($expected, $actual->isArchiveBomb());

        // In memory scanning
        $tempFile = new SplTempFileObject();
        $tempFile->fwrite(file_get_contents($filename));
        $actual = $scanner->scanFile($file);

        self::assertSame($expected, $actual->isArchiveBomb());
    }

    /**
     * Provider.
     *
     * @return array
     */
    public function providerGetImageTypeFromFile(): array
    {
        return [
            [__DIR__ . '/files/10.zip', true],
            [__DIR__ . '/files/20.zip', true],
            [__DIR__ . '/files/30.zip', true],
            [__DIR__ . '/files/34.zip', true],
            [__DIR__ . '/files/42.zip', true],
            [__DIR__ . '/files/ok.zip', false],
            [__DIR__ . '/files/ok-encrypted.zip', false],
        ];
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testScanFileNotFound(): void
    {
        static::getExpectedException(RuntimeException::class);
        static::expectExceptionMessage('File not found: temp.zip');

        $scanner = new ArchiveBombScanner();
        $scanner->addEngine(new ZipBombEngine());

        $filename = __DIR__ . '/temp.zip';
        touch($filename);

        $file = new SplFileObject($filename);

        unlink($filename);

        $scanner->scanFile($file);
    }
}
