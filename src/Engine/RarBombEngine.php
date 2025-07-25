<?php

namespace Selective\ArchiveBomb\Engine;

use RuntimeException;
use Selective\ArchiveBomb\Scanner\BombScannerResult;
use Selective\Rar\RarFileReader;
use SplFileObject;

/**
 * RAR bomb scanner.
 */
final class RarBombEngine implements EngineInterface
{
    /**
     * @var int
     */
    private int $maxRatio;

    /**
     * The constructor.
     *
     * @param int $maxRatio The max Bomb size ratio (Original size / Compressed size)
     */
    public function __construct(int $maxRatio = 1000)
    {
        $this->maxRatio = $maxRatio;
    }

    /**
     * Scan for RAR bomb.
     *
     * @param SplFileObject $file The rar file
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

        if (!$this->isRar($file)) {
            return new BombScannerResult(false);
        }

        $fileReader = new RarFileReader();
        $rarArchive = $fileReader->openFile($file);

        // http://web.archive.org/web/20201223151701/https://aerasec.de/security/advisories/decompression-bomb-vulnerability.html
        foreach ($rarArchive->getEntries() as $entry) {
            $compressedSize = $entry->getPackedSize();
            $originalSize = (float)$entry->getUnpackedSize();
            $ration = $originalSize / $compressedSize;

            if ($ration >= $this->maxRatio) {
                return new BombScannerResult($ration >= $this->maxRatio);
            }
        }

        return new BombScannerResult(false);
    }

    /**
     * Detect file type.
     *
     * @param SplFileObject $file The file
     *
     * @return bool The status
     */
    private function isRar(SplFileObject $file): bool
    {
        $file->rewind();

        return $file->fread(3) === "\x52\x61\x72";
    }
}
