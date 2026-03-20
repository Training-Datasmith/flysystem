<?php

declare (strict_types=1);
namespace League\Flysystem;

abstract class Decorated_Adapter implements Filesystem_Adapter
{
    public function __construct(protected Filesystem_Adapter $adapter)
    {
    }
    public function file_exists(string $path): bool
    {
        return $this->adapter->file_exists($path);
    }
    public function directory_exists(string $path): bool
    {
        return $this->adapter->directory_exists($path);
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->adapter->write_stream($path, $contents, $config);
    }
    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }
    public function read_stream(string $path)
    {
        return $this->adapter->read_stream($path);
    }
    public function delete(string $path): void
    {
        $this->adapter->delete($path);
    }
    public function delete_directory(string $path): void
    {
        $this->adapter->delete_directory($path);
    }
    public function create_directory(string $path, Config $config): void
    {
        $this->adapter->create_directory($path, $config);
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $this->adapter->set_visibility($path, $visibility);
    }
    public function visibility(string $path): File_Attributes
    {
        return $this->adapter->visibility($path);
    }
    public function mime_type(string $path): File_Attributes
    {
        return $this->adapter->mime_type($path);
    }
    public function last_modified(string $path): File_Attributes
    {
        return $this->adapter->last_modified($path);
    }
    public function file_size(string $path): File_Attributes
    {
        return $this->adapter->file_size($path);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        return $this->adapter->list_contents($path, $deep);
    }
    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
    }
}