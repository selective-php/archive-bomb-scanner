<?php

namespace Selective\ArchiveBomb\Engine;

use RuntimeException;
use Selective\ArchiveBomb\Scanner\BombScannerResult;
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
     * @return BombScannerResult The result
     */
    public function scanFile(SplFileObject $file): BombScannerResult
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException(sprintf('File not found: %s', $file->getFilename()));
        }

        if (!$this->isZip($file)) {
            return new BombScannerResult(false);
        }

        $zip = $this->openZip($realPath);

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

        return new BombScannerResult($result);
    }

    /**
     * Open zip file.
     *
     * @param string $filename The zip file
     *
     * @throws RuntimeException
     *
     * @return ZipArchive The zip archive
     */
    private function openZip(string $filename): ZipArchive
    {
        $zip = new ZipArchive();
        $result = $zip->open($filename, ZIPARCHIVE::CREATE);

        if ($result !== true) {
            $errorMap = [
                ZipArchive::ER_EXISTS => 'File already exists',
                ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
                ZipArchive::ER_INVAL => 'Invalid argument.',
                ZipArchive::ER_MEMORY => 'Malloc failure.',
                ZipArchive::ER_NOENT => 'No such file.',
                ZipArchive::ER_NOZIP => 'Not a zip archive.',
                ZipArchive::ER_OPEN => 'Can\'t open file.',
                ZipArchive::ER_READ => 'Read error.',
                ZipArchive::ER_SEEK => 'Seek error.',
            ];

            $errorReason = $errorMap[(int)$result] ?? 'Unknown error.';

            throw new RuntimeException(sprintf('Unable to open: %s, reason: %s', $filename, $errorReason));
        }

        return $zip;
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
