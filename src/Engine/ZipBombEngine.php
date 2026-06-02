<?php

namespace Selective\ArchiveBomb\Engine;

use RuntimeException;
use Selective\ArchiveBomb\Scanner\BombScannerResult;
use SplFileObject;

/**
 * ZIP bomb scanner.
 */
final class ZipBombEngine implements EngineInterface
{
    private const MAX_RECURSION_DEPTH = 20;
    private const MAX_NESTED_ARCHIVE_SIZE = 104857600;

    /**
     * @var array<string, float>
     */
    private array $nestedArchiveSizes = [];

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

        $fileSize = $file->getSize();
        $result = $fileSize > 0 && $this->getOriginalSizeFromFile($file, $fileSize * $this->maxRatio) >=
            $fileSize * $this->maxRatio;

        return new BombScannerResult($result);
    }

    /**
     * Read original archive size from file central directory metadata.
     *
     * @param SplFileObject $file The zip file
     * @param float $maxSize The maximum size to read
     * @param int $depth The recursion depth
     *
     * @throws RuntimeException
     *
     * @return float The original size
     */
    private function getOriginalSizeFromFile(SplFileObject $file, float $maxSize, int $depth = 0): float
    {
        $entries = $this->readFileCentralDirectoryEntries($file);

        if ($entries === []) {
            return (float)$file->getSize();
        }

        $size = 0.0;

        foreach ($entries as $entry) {
            $entryCompressedSize = (int)$entry['compressed_size'];
            $entryOriginalSize = (int)$entry['original_size'];

            if ($entryCompressedSize === 0 && $entryOriginalSize > 0) {
                return $maxSize;
            }

            if ($entryCompressedSize === 0) {
                continue;
            }

            $entryOriginalSize = $this->getFileEntryOriginalSize($file, $entry, $depth, $maxSize - $size);
            $ratio = $entryOriginalSize / $entryCompressedSize;

            if ($ratio >= $this->maxRatio) {
                return $maxSize;
            }

            $size += $entryOriginalSize;

            if ($size >= $maxSize) {
                return $maxSize;
            }
        }

        return $size;
    }

    /**
     * Read original entry size from a file ZIP archive.
     *
     * @param SplFileObject $file The zip file
     * @param array<string, int|string> $entry The central directory entry
     * @param int $depth The recursion depth
     * @param float $maxSize The maximum size to read
     *
     * @throws RuntimeException
     *
     * @return float The original size
     */
    private function getFileEntryOriginalSize(SplFileObject $file, array $entry, int $depth, float $maxSize): float
    {
        $originalSize = (int)$entry['original_size'];

        if ($depth >= self::MAX_RECURSION_DEPTH || $originalSize > self::MAX_NESTED_ARCHIVE_SIZE) {
            return (float)$originalSize;
        }

        $name = (string)$entry['name'];

        if (!str_ends_with(strtolower($name), '.zip')) {
            return (float)$originalSize;
        }

        $contents = $this->getFileEntryContents($file, $entry);

        if (!is_string($contents) || !str_starts_with($contents, "PK\3\4")) {
            return (float)$originalSize;
        }

        return $this->getOriginalSizeFromContents($contents, $maxSize, $depth + 1);
    }

    /**
     * Read ZIP central directory entries from a file.
     *
     * @param SplFileObject $file The zip file
     *
     * @return array<int, array<string, int|string>> The central directory entries
     */
    private function readFileCentralDirectoryEntries(SplFileObject $file): array
    {
        $fileSize = $file->getSize();

        if ($fileSize === false) {
            return [];
        }

        $tailSize = min($fileSize, 65557);
        $tailOffset = $fileSize - $tailSize;
        $tail = $this->readFileBytes($file, $tailOffset, $tailSize);
        $relativeEocdOffset = $this->findEndOfCentralDirectoryOffset($tail);

        if ($relativeEocdOffset === null || strlen($tail) < $relativeEocdOffset + 22) {
            return [];
        }

        $entryCount = $this->readUnsignedShort($tail, $relativeEocdOffset + 10);
        $centralDirectoryOffset = $this->readUnsignedLong($tail, $relativeEocdOffset + 16);
        $entries = [];
        $offset = $centralDirectoryOffset;

        for ($i = 0; $i < $entryCount; $i++) {
            $entry = $this->readFileCentralDirectoryEntry($file, $offset);

            if ($entry === null) {
                return [];
            }

            $entries[] = $entry;
            $offset += 46 + (int)$entry['name_length'] + (int)$entry['extra_length'] + (int)$entry['comment_length'];
        }

        return $entries;
    }

    /**
     * Read a ZIP central directory entry from a file.
     *
     * @param SplFileObject $file The zip file
     * @param int $offset The central directory entry offset
     *
     * @return array<string, int|string>|null The entry
     */
    private function readFileCentralDirectoryEntry(SplFileObject $file, int $offset): ?array
    {
        $header = $this->readFileBytes($file, $offset, 46);

        if (strlen($header) < 46 || substr($header, 0, 4) !== "PK\1\2") {
            return null;
        }

        $nameLength = $this->readUnsignedShort($header, 28);
        $extraLength = $this->readUnsignedShort($header, 30);
        $commentLength = $this->readUnsignedShort($header, 32);
        $name = $this->readFileBytes($file, $offset + 46, $nameLength);

        if (strlen($name) < $nameLength) {
            return null;
        }

        return [
            'flags' => $this->readUnsignedShort($header, 8),
            'method' => $this->readUnsignedShort($header, 10),
            'compressed_size' => $this->readUnsignedLong($header, 20),
            'original_size' => $this->readUnsignedLong($header, 24),
            'name_length' => $nameLength,
            'extra_length' => $extraLength,
            'comment_length' => $commentLength,
            'local_header_offset' => $this->readUnsignedLong($header, 42),
            'name' => $name,
        ];
    }

    /**
     * Read an entry contents from a file ZIP archive.
     *
     * @param SplFileObject $file The zip file
     * @param array<string, int|string> $entry The central directory entry
     *
     * @return string|null The entry contents
     */
    private function getFileEntryContents(SplFileObject $file, array $entry): ?string
    {
        $flags = (int)$entry['flags'];

        if (($flags & 1) === 1) {
            return null;
        }

        $offset = (int)$entry['local_header_offset'];
        $header = $this->readFileBytes($file, $offset, 30);

        if (strlen($header) < 30 || substr($header, 0, 4) !== "PK\3\4") {
            return null;
        }

        $nameLength = $this->readUnsignedShort($header, 26);
        $extraLength = $this->readUnsignedShort($header, 28);
        $payloadOffset = $offset + 30 + $nameLength + $extraLength;
        $payload = $this->readFileBytes($file, $payloadOffset, (int)$entry['compressed_size']);

        if (strlen($payload) < (int)$entry['compressed_size']) {
            return null;
        }

        return $this->decompressEntryPayload($payload, (int)$entry['method']);
    }

    /**
     * Read original ZIP archive size from nested archive contents.
     *
     * @param string $contents The zip archive contents
     * @param float $maxSize The maximum size to read
     * @param int $depth The recursion depth
     *
     * @throws RuntimeException
     *
     * @return float The original size
     */
    private function getOriginalSizeFromContents(string $contents, float $maxSize, int $depth): float
    {
        $key = sha1($contents);

        if (isset($this->nestedArchiveSizes[$key])) {
            return min($this->nestedArchiveSizes[$key], $maxSize);
        }

        $result = $this->getOriginalSizeFromZipContents($contents, $maxSize, $depth);
        $this->nestedArchiveSizes[$key] = $result;

        return $result;
    }

    /**
     * Read original ZIP archive size from in-memory ZIP archive contents.
     *
     * @param string $contents The zip archive contents
     * @param float $maxSize The maximum size to read
     * @param int $depth The recursion depth
     *
     * @return float The original size
     */
    private function getOriginalSizeFromZipContents(string $contents, float $maxSize, int $depth): float
    {
        $entries = $this->readCentralDirectoryEntries($contents);

        if ($entries === []) {
            return (float)strlen($contents);
        }

        $size = 0.0;

        foreach ($entries as $entry) {
            $entryCompressedSize = (int)$entry['compressed_size'];
            $entryOriginalSize = (int)$entry['original_size'];

            if ($entryCompressedSize === 0 && $entryOriginalSize > 0) {
                return $maxSize;
            }

            if ($entryCompressedSize === 0) {
                continue;
            }

            $entryOriginalSize = $this->getMemoryEntryOriginalSize($contents, $entry, $depth, $maxSize - $size);
            $ratio = $entryOriginalSize / $entryCompressedSize;

            if ($ratio >= $this->maxRatio) {
                return $maxSize;
            }

            $size += $entryOriginalSize;

            if ($size >= $maxSize) {
                return $maxSize;
            }
        }

        return $size;
    }

    /**
     * Read original entry size from in-memory ZIP archive contents.
     *
     * @param string $contents The zip archive contents
     * @param array<string, int|string> $entry The central directory entry
     * @param int $depth The recursion depth
     * @param float $maxSize The maximum size to read
     *
     * @return float The original size
     */
    private function getMemoryEntryOriginalSize(string $contents, array $entry, int $depth, float $maxSize): float
    {
        $originalSize = (int)$entry['original_size'];

        if ($depth >= self::MAX_RECURSION_DEPTH || $originalSize > self::MAX_NESTED_ARCHIVE_SIZE) {
            return (float)$originalSize;
        }

        $name = (string)$entry['name'];

        if (!str_ends_with(strtolower($name), '.zip')) {
            return (float)$originalSize;
        }

        $nestedContents = $this->getMemoryEntryContents($contents, $entry);

        if (!is_string($nestedContents) || !str_starts_with($nestedContents, "PK\3\4")) {
            return (float)$originalSize;
        }

        return $this->getOriginalSizeFromContents($nestedContents, $maxSize, $depth + 1);
    }

    /**
     * Read ZIP central directory entries from in-memory ZIP archive contents.
     *
     * @param string $contents The zip archive contents
     *
     * @return array<int, array<string, int|string>> The central directory entries
     */
    private function readCentralDirectoryEntries(string $contents): array
    {
        $eocdOffset = $this->findEndOfCentralDirectoryOffset($contents);

        if ($eocdOffset === null || strlen($contents) < $eocdOffset + 22) {
            return [];
        }

        $entryCount = $this->readUnsignedShort($contents, $eocdOffset + 10);
        $centralDirectoryOffset = $this->readUnsignedLong($contents, $eocdOffset + 16);
        $entries = [];
        $offset = $centralDirectoryOffset;

        for ($i = 0; $i < $entryCount; $i++) {
            $entry = $this->readCentralDirectoryEntry($contents, $offset);

            if ($entry === null) {
                return [];
            }

            $entries[] = $entry;
            $offset += 46 + (int)$entry['name_length'] + (int)$entry['extra_length'] + (int)$entry['comment_length'];
        }

        return $entries;
    }

    /**
     * Read a ZIP central directory entry.
     *
     * @param string $contents The zip archive contents
     * @param int $offset The central directory entry offset
     *
     * @return array<string, int|string>|null The entry
     */
    private function readCentralDirectoryEntry(string $contents, int $offset): ?array
    {
        if (strlen($contents) < $offset + 46 || substr($contents, $offset, 4) !== "PK\1\2") {
            return null;
        }

        $nameLength = $this->readUnsignedShort($contents, $offset + 28);
        $extraLength = $this->readUnsignedShort($contents, $offset + 30);
        $commentLength = $this->readUnsignedShort($contents, $offset + 32);
        $nameOffset = $offset + 46;

        if (strlen($contents) < $nameOffset + $nameLength + $extraLength + $commentLength) {
            return null;
        }

        return [
            'flags' => $this->readUnsignedShort($contents, $offset + 8),
            'method' => $this->readUnsignedShort($contents, $offset + 10),
            'compressed_size' => $this->readUnsignedLong($contents, $offset + 20),
            'original_size' => $this->readUnsignedLong($contents, $offset + 24),
            'name_length' => $nameLength,
            'extra_length' => $extraLength,
            'comment_length' => $commentLength,
            'local_header_offset' => $this->readUnsignedLong($contents, $offset + 42),
            'name' => substr($contents, $nameOffset, $nameLength),
        ];
    }

    /**
     * Read an in-memory ZIP entry contents.
     *
     * @param string $contents The zip archive contents
     * @param array<string, int|string> $entry The central directory entry
     *
     * @return string|null The entry contents
     */
    private function getMemoryEntryContents(string $contents, array $entry): ?string
    {
        $flags = (int)$entry['flags'];

        if (($flags & 1) === 1) {
            return null;
        }

        $offset = (int)$entry['local_header_offset'];

        if (strlen($contents) < $offset + 30 || substr($contents, $offset, 4) !== "PK\3\4") {
            return null;
        }

        $nameLength = $this->readUnsignedShort($contents, $offset + 26);
        $extraLength = $this->readUnsignedShort($contents, $offset + 28);
        $payloadOffset = $offset + 30 + $nameLength + $extraLength;
        $compressedSize = (int)$entry['compressed_size'];

        if (strlen($contents) < $payloadOffset + $compressedSize) {
            return null;
        }

        $payload = substr($contents, $payloadOffset, $compressedSize);

        return $this->decompressEntryPayload($payload, (int)$entry['method']);
    }

    /**
     * Decompress a ZIP entry payload.
     *
     * @param string $payload The compressed payload
     * @param int $method The compression method
     *
     * @return string|null The decompressed payload
     */
    private function decompressEntryPayload(string $payload, int $method): ?string
    {
        if ($method === 0) {
            return $payload;
        }

        if ($method !== 8) {
            return null;
        }

        $result = gzinflate($payload);

        return is_string($result) ? $result : null;
    }

    /**
     * Find the end of central directory record offset.
     *
     * @param string $contents The zip archive contents
     *
     * @return int|null The offset
     */
    private function findEndOfCentralDirectoryOffset(string $contents): ?int
    {
        $length = strlen($contents);
        $start = max(0, $length - 65557);

        for ($offset = $length - 22; $offset >= $start; $offset--) {
            if (substr($contents, $offset, 4) === "PK\5\6") {
                return $offset;
            }
        }

        return null;
    }

    /**
     * Read bytes from a file.
     *
     * @param SplFileObject $file The file
     * @param int $offset The offset
     * @param int $length The length
     *
     * @return string The bytes
     */
    private function readFileBytes(SplFileObject $file, int $offset, int $length): string
    {
        $file->fseek($offset);

        $contents = $file->fread($length);

        return is_string($contents) ? $contents : '';
    }

    /**
     * Read an unsigned little-endian short.
     *
     * @param string $contents The contents
     * @param int $offset The offset
     *
     * @return int The value
     */
    private function readUnsignedShort(string $contents, int $offset): int
    {
        return unpack('v', substr($contents, $offset, 2))[1];
    }

    /**
     * Read an unsigned little-endian long.
     *
     * @param string $contents The contents
     * @param int $offset The offset
     *
     * @return int The value
     */
    private function readUnsignedLong(string $contents, int $offset): int
    {
        return unpack('V', substr($contents, $offset, 4))[1];
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
