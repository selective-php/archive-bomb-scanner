<?php

namespace Selective\ArchiveBomb\Engine;

use Selective\ArchiveBomb\Scanner\ScannerResult;
use SplFileObject;
use ZipArchive;

/**
 * ZIP bomb scanner.
 */
final class ZipBombEngine implements EngineInterface
{
    /**
     * Scan for ZIP bomb.
     *
     * @param SplFileObject $file The zip file
     *
     * @return ScannerResult The result
     */
    public function scanFile(SplFileObject $file): ?ScannerResult
    {
        $zip = new ZipArchive();
        $zip->open($file->getRealPath(), ZIPARCHIVE::CHECKCONS);

        // Sum ZIP index file size
        $i = 0;
        $size = 0;
        while ($idx = $zip->statIndex($i++)) {
            $size += $idx['size'];
        }

        // Reading the ZIP header
        // https://en.wikipedia.org/wiki/Zip_(file_format)#File_headers
        // Offset 22, 4 bytes: Uncompressed size
        $file->rewind();
        $file->fread(22);

        // Convert 4 bytes, little-endian to int
        $size2 = unpack('V', $file->fread(4))[1];

        // Header uncompressed size must be the same as files uncompressed size
        $result = $size !== $size2;

        return new ScannerResult($result);
    }
}
