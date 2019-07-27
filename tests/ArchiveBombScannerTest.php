<?php

namespace Selective\ArchiveBomb\Test;

use PHPUnit\Framework\TestCase;
use Selective\ArchiveBomb\Engine\ZipBombEngine;
use Selective\ArchiveBomb\Scanner\ArchiveBombScanner;
use SplFileObject;

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
}
