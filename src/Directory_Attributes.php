<?php

declare (strict_types=1);
namespace League\Flysystem;

class Directory_Attributes implements Storage_Attributes
{
    use Proxy_Array_Access_To_Properties;
    private string $type = Storage_Attributes::TYPE_DIRECTORY;
    public function __construct(private string $path, private ?string $visibility = null, private ?int $last_modified = null, private array $extra_metadata = [])
    {
        $this->path = trim($this->path, '/');
    }
    public function path(): string
    {
        return $this->path;
    }
    public function type(): string
    {
        return $this->type;
    }
    public function visibility(): ?string
    {
        return $this->visibility;
    }
    public function last_modified(): ?int
    {
        return $this->last_modified;
    }
    public function extra_metadata(): array
    {
        return $this->extra_metadata;
    }
    public function is_file(): bool
    {
        return false;
    }
    public function is_dir(): bool
    {
        return true;
    }
    public function with_path(string $path): self
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }
    public static function from_array(array $attributes): self
    {
        return new Directory_Attributes($attributes[Storage_Attributes::ATTRIBUTE_PATH], $attributes[Storage_Attributes::ATTRIBUTE_VISIBILITY] ?? null, $attributes[Storage_Attributes::ATTRIBUTE_LAST_MODIFIED] ?? null, $attributes[Storage_Attributes::ATTRIBUTE_EXTRA_METADATA] ?? []);
    }
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [Storage_Attributes::ATTRIBUTE_TYPE => $this->type, Storage_Attributes::ATTRIBUTE_PATH => $this->path, Storage_Attributes::ATTRIBUTE_VISIBILITY => $this->visibility, Storage_Attributes::ATTRIBUTE_LAST_MODIFIED => $this->last_modified, Storage_Attributes::ATTRIBUTE_EXTRA_METADATA => $this->extra_metadata];
    }
}