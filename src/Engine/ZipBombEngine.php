<?php

namespace Selective\ArchiveBomb\Engine;

use RuntimeException;
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
     * @throws RuntimeException
     *
     * @return ScannerResult The result
     */
    public function scanFile(SplFileObject $file): ScannerResult
    {
        $zip = new ZipArchive();

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException(sprintf('File not found: %s', $file->getFilename()));
        }

        if (!$this->isZip($file)) {
            return new ScannerResult(false);
        }

        $zip->open($realPath, ZIPARCHIVE::CHECKCONS);

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
        $size2 = unpack('V', (string)$file->fread(4))[1];

        // Header uncompressed size must be the same as files uncompressed size
        $result = $size !== $size2;

        return new ScannerResult($result);
    }

    /**
     * Detect file type.
     *
     * @param SplFileObject $file The file
     *
     * @return bool The status
     */
    private function isZip(SplFileObject $file): bool
    {
        $file->rewind();

        return $file->fread(4) === "PK\3\4";
    }
}
