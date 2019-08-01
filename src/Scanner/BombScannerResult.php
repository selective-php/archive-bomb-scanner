<?php

namespace Selective\ArchiveBomb\Scanner;

/**
 * Scanner result value object.
 */
final class BombScannerResult
{
    /**
     * @var bool The result
     */
    private $isBomb;

    /**
     * ImageType constructor.
     *
     * @param bool $isBomb The result
     */
    public function __construct(bool $isBomb)
    {
        $this->isBomb = $isBomb;
    }

    /**
     * Get result.
     *
     * @return bool The result
     */
    public function isBomb(): bool
    {
        return $this->isBomb;
    }

    /**
     * Compare with other value object.
     *
     * @param BombScannerResult $other The other type
     *
     * @return bool Status
     */
    public function equals(BombScannerResult $other): bool
    {
        return $this->isBomb === $other->isBomb;
    }
}
