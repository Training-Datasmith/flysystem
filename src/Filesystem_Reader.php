<?php

declare (strict_types=1);
namespace League\Flysystem;

use DateTimeInterface;
/**
 * This interface contains everything to read from and inspect
 * a filesystem. All methods containing are non-destructive.
 *
 * @method string publicUrl(string $path, array $config = []) Will be added in 4.0
 * @method string temporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []) Will be added in 4.0
 * @method string checksum(string $path, array $config = []) Will be added in 4.0
 */
interface Filesystem_Reader
{
    public const LIST_SHALLOW = false;
    public const LIST_DEEP = true;
    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function file_exists(string $location): bool;
    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function directory_exists(string $location): bool;
    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function has(string $location): bool;
    /**
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read(string $location): string;
    /**
     * @return resource
     *
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read_stream(string $location);
    /**
     * @return DirectoryListing<StorageAttributes>
     *
     * @throws FilesystemException
     * @throws UnableToListContents
     */
    public function list_contents(string $location, bool $deep = self::LIST_SHALLOW): Directory_Listing;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function last_modified(string $path): int;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function file_size(string $path): int;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mime_type(string $path): string;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): string;
}