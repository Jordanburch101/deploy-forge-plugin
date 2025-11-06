<?php

/**
 * PHP Built-in Classes Stubs
 * 
 * This file provides type hints for PHP's built-in classes that may not be 
 * recognized by all linters. These classes are part of PHP core/SPL and are 
 * available in standard PHP installations.
 * 
 * This file should never be loaded at runtime - it's purely for static analysis.
 */

// Prevent accidental execution
if (false) {

    /**
     * ZipArchive class - from PHP zip extension
     * @link https://www.php.net/manual/en/class.ziparchive.php
     */
    class ZipArchive
    {
        public const CREATE = 1;
        public const OVERWRITE = 8;
        public const EXCL = 2;
        public const CHECKCONS = 4;

        public function open(string $filename, int $flags = 0): bool|int {}
        public function close(): bool {}
        public function extractTo(string $destination, array|string|null $files = null): bool {}
        public function addFile(string $filepath, string $entryname = '', int $start = 0, int $length = 0, int $flags = 0): bool {}
    }

    /**
     * RecursiveIterator interface - from PHP SPL
     * @link https://www.php.net/manual/en/class.recursiveiterator.php
     */
    interface RecursiveIterator extends Iterator
    {
        public function hasChildren(): bool;
        public function getChildren();
    }

    /**
     * RecursiveDirectoryIterator - from PHP SPL
     * @link https://www.php.net/manual/en/class.recursivedirectoryiterator.php
     */
    class RecursiveDirectoryIterator extends FilesystemIterator implements RecursiveIterator, SeekableIterator
    {
        public const SKIP_DOTS = 4096;
        public const CURRENT_AS_PATHNAME = 32;
        public const CURRENT_AS_FILEINFO = 0;
        public const CURRENT_AS_SELF = 16;

        public function __construct(string $directory, int $flags = 0) {}
        public function hasChildren(bool $allowLinks = false): bool {}
        public function getChildren() {}
    }

    /**
     * OuterIterator interface - from PHP SPL
     * @link https://www.php.net/manual/en/class.outeriterator.php
     */
    interface OuterIterator extends Iterator
    {
        public function getInnerIterator();
    }

    /**
     * RecursiveIteratorIterator - from PHP SPL
     * @link https://www.php.net/manual/en/class.recursiveiteratoriterator.php
     */
    class RecursiveIteratorIterator implements OuterIterator
    {
        public const LEAVES_ONLY = 0;
        public const SELF_FIRST = 1;
        public const CHILD_FIRST = 2;
        public const CATCH_GET_CHILD = 16;

        public function __construct(Traversable|RecursiveIterator $iterator, int $mode = self::LEAVES_ONLY, int $flags = 0) {}
        public function getSubPathname(): string {}
        public function current(): mixed {}
        public function key(): mixed {}
        public function next(): void {}
        public function rewind(): void {}
        public function valid(): bool {}
        public function getInnerIterator() {}
    }

    /**
     * Iterator interface - from PHP SPL
     * @link https://www.php.net/manual/en/class.iterator.php
     */
    interface Iterator extends Traversable
    {
        public function current(): mixed;
        public function key(): mixed;
        public function next(): void;
        public function rewind(): void;
        public function valid(): bool;
    }

    /**
     * SeekableIterator interface - from PHP SPL
     * @link https://www.php.net/manual/en/class.seekableiterator.php
     */
    interface SeekableIterator extends Iterator
    {
        public function seek(int $offset): void;
    }

    /**
     * FilesystemIterator - from PHP SPL
     * @link https://www.php.net/manual/en/class.filesystemiterator.php
     */
    class FilesystemIterator extends DirectoryIterator implements SeekableIterator
    {
        public function current() {}
        public function isFile(): bool {}
        public function isDir(): bool {}
        public function getRealPath(): string|false {}
    }

    /**
     * SplFileInfo - from PHP SPL
     * @link https://www.php.net/manual/en/class.splfileinfo.php
     */
    class SplFileInfo
    {
        public function __construct(string $filename) {}
        public function getPath(): string {}
        public function getFilename(): string {}
        public function getRealPath(): string|false {}
        public function isFile(): bool {}
        public function isDir(): bool {}
    }

    /**
     * SplFileObject - from PHP SPL
     * @link https://www.php.net/manual/en/class.splfileobject.php
     */
    class SplFileObject extends SplFileInfo implements SeekableIterator
    {
        public function __construct(string $filename, string $mode = 'r', bool $useIncludePath = false, $context = null) {}
        public function current(): mixed {}
        public function key(): mixed {}
        public function next(): void {}
        public function rewind(): void {}
        public function valid(): bool {}
        public function seek(int $line): void {}
        public function eof(): bool {}
        public function fgets(): string {}
        public function fgetcsv(string $separator = ',', string $enclosure = '"', string $escape = '\\'): array|false {}
        public function fwrite(string $data, int $length = 0): int|false {}
    }

    /**
     * DirectoryIterator - from PHP SPL
     * @link https://www.php.net/manual/en/class.directoryiterator.php
     */
    class DirectoryIterator extends SplFileInfo implements SeekableIterator
    {
        public function __construct(string $directory) {}
        public function current(): mixed {}
        public function key(): mixed {}
        public function next(): void {}
        public function rewind(): void {}
        public function valid(): bool {}
        public function seek(int $offset): void {}
    }
} // end if (false)
