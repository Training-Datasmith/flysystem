<?php

declare (strict_types=1);
namespace League\Flysystem;

interface Filesystem_Writer
{
    /**
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $location, string $contents, array $config = []): void;
    /**
     * @param mixed $contents
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write_stream(string $location, $contents, array $config = []): void;
    /**
     * @throws UnableToSetVisibility
     * @throws FilesystemException
     */
    public function set_visibility(string $path, string $visibility): void;
    /**
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $location): void;
    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function delete_directory(string $location): void;
    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function create_directory(string $location, array $config = []): void;
    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, array $config = []): void;
    /**
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, array $config = []): void;
}