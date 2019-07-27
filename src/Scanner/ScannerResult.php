<?php

namespace Selective\ArchiveBomb\Scanner;

/**
 * Scanner result value object.
 */
final class ScannerResult
{
    /**
     * @var bool The result
     */
    private $isArchiveBomb;

    /**
     * ImageType constructor.
     *
     * @param bool $isArchiveBomb The result
     */
    public function __construct(bool $isArchiveBomb)
    {
        $this->isArchiveBomb = $isArchiveBomb;
    }

    /**
     * Get result.
     *
     * @return bool The result
     */
    public function isArchiveBomb(): bool
    {
        return $this->isArchiveBomb;
    }

    /**
     * Compare with other image type.
     *
     * @param ScannerResult $other The other type
     *
     * @return bool Status
     */
    public function equals(ScannerResult $other): bool
    {
        return $this->isArchiveBomb === $other->isArchiveBomb;
    }
}
