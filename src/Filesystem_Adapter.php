<?php

declare (strict_types=1);
namespace League\Flysystem;

interface Filesystem_Adapter
{
    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function file_exists(string $path): bool;
    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function directory_exists(string $path): bool;
    /**
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $path, string $contents, Config $config): void;
    /**
     * @param resource $contents
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write_stream(string $path, $contents, Config $config): void;
    /**
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read(string $path): string;
    /**
     * @return resource
     *
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read_stream(string $path);
    /**
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $path): void;
    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function delete_directory(string $path): void;
    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function create_directory(string $path, Config $config): void;
    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function set_visibility(string $path, string $visibility): void;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): File_Attributes;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mime_type(string $path): File_Attributes;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function last_modified(string $path): File_Attributes;
    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function file_size(string $path): File_Attributes;
    /**
     * @return iterable<StorageAttributes>
     *
     * @throws FilesystemException
     */
    public function list_contents(string $path, bool $deep): iterable;
    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void;
    /**
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void;
}