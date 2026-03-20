<?php

declare (strict_types=1);
namespace League\Flysystem\Adapter_Test_Utilities;

use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Filesystem_Operation_Failed;
class Exception_Throwing_Filesystem_Adapter implements Filesystem_Adapter
{
    /**
     * @var array<string, FilesystemOperationFailed>
     */
    private array $staged_exceptions = [];
    public function __construct(private Filesystem_Adapter $adapter)
    {
    }
    public function stage_exception(string $method, string $path, Filesystem_Operation_Failed $exception): void
    {
        $this->staged_exceptions[join('@', [$method, $path])] = $exception;
    }
    private function throw_staged_exception(string $method, string $path): void
    {
        $method = preg_replace('~.+::~', '', $method);
        $key = join('@', [$method, $path]);
        if (!array_key_exists($key, $this->staged_exceptions)) {
            return;
        }
        $exception = $this->staged_exceptions[$key];
        unset($this->staged_exceptions[$key]);
        throw $exception;
    }
    public function file_exists(string $path): bool
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->file_exists($path);
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->write($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->write_stream($path, $contents, $config);
    }
    public function read(string $path): string
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->read($path);
    }
    public function read_stream(string $path)
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->read_stream($path);
    }
    public function delete(string $path): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->delete($path);
    }
    public function delete_directory(string $path): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->delete_directory($path);
    }
    public function create_directory(string $path, Config $config): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->create_directory($path, $config);
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $this->throw_staged_exception(__METHOD__, $path);
        $this->adapter->set_visibility($path, $visibility);
    }
    public function visibility(string $path): File_Attributes
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->visibility($path);
    }
    public function mime_type(string $path): File_Attributes
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->mime_type($path);
    }
    public function last_modified(string $path): File_Attributes
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->last_modified($path);
    }
    public function file_size(string $path): File_Attributes
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->file_size($path);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->list_contents($path, $deep);
    }
    public function move(string $source, string $destination, Config $config): void
    {
        $this->throw_staged_exception(__METHOD__, $source);
        $this->adapter->move($source, $destination, $config);
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->throw_staged_exception(__METHOD__, $source);
        $this->adapter->copy($source, $destination, $config);
    }
    public function directory_exists(string $path): bool
    {
        $this->throw_staged_exception(__METHOD__, $path);
        return $this->adapter->directory_exists($path);
    }
}