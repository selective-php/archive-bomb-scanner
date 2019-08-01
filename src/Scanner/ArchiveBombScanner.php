<?php

namespace Selective\ArchiveBomb\Scanner;

use Selective\ArchiveBomb\Engine\EngineInterface;
use SplFileObject;

/**
 * Archive bomb scanner.
 */
final class ArchiveBombScanner
{
    /**
     * @var EngineInterface[]
     */
    private $engines = [];

    /**
     * Add scanner engine.
     *
     * @param EngineInterface $engine The scanner engine
     */
    public function addEngine(EngineInterface $engine): void
    {
        $this->engines[] = $engine;
    }

    /**
     * Scan archive file.
     *
     * @param SplFileObject $file The archive file
     *
     * @return ScannerResult The scanning result
     */
    public function scanFile(SplFileObject $file): ScannerResult
    {
        foreach ($this->engines as $engines) {
            $result = $engines->scanFile($file);

            if ($result->isBomb()) {
                return $result;
            }
        }

        return new ScannerResult(false);
    }
}
