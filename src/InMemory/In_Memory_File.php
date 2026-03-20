<?php

declare (strict_types=1);
namespace League\Flysystem\In_Memory;

use const FILEINFO_MIME_TYPE;
use finfo;
/**
 * @internal
 */
class In_Memory_File
{
    private string $contents = '';
    private int $last_modified = 0;
    private ?string $visibility = null;
    public function update_contents(string $contents, ?int $timestamp): void
    {
        $this->contents = $contents;
        $this->last_modified = $timestamp ?? time();
    }
    public function last_modified(): int
    {
        return $this->last_modified;
    }
    public function with_last_modified(int $last_modified): self
    {
        $clone = clone $this;
        $clone->last_modified = $last_modified;
        return $clone;
    }
    public function read(): string
    {
        return $this->contents;
    }
    /**
     * @return resource
     */
    public function read_stream()
    {
        /** @var resource $stream */
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->contents);
        rewind($stream);
        return $stream;
    }
    public function file_size(): int
    {
        return strlen($this->contents);
    }
    public function mime_type(): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($this->contents);
    }
    public function set_visibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }
    public function visibility(): ?string
    {
        return $this->visibility;
    }
}