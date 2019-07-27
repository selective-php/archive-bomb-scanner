<?php

namespace Selective\ArchiveBomb\Engine;

use Selective\ArchiveBomb\Scanner\ScannerResult;
use SplFileObject;

/**
 * Engine.
 */
interface EngineInterface
{
    /**
     * Detect.
     *
     * @param SplFileObject $file The file
     *
     * @return ScannerResult The result
     */
    public function scanFile(SplFileObject $file): ?ScannerResult;
}
