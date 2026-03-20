<?php

declare (strict_types=1);
namespace League\Flysystem;

use ArrayAccess;
use JsonSerializable;
interface Storage_Attributes extends JsonSerializable, ArrayAccess
{
    public const ATTRIBUTE_PATH = 'path';
    public const ATTRIBUTE_TYPE = 'type';
    public const ATTRIBUTE_FILE_SIZE = 'file_size';
    public const ATTRIBUTE_VISIBILITY = 'visibility';
    public const ATTRIBUTE_LAST_MODIFIED = 'last_modified';
    public const ATTRIBUTE_MIME_TYPE = 'mime_type';
    public const ATTRIBUTE_EXTRA_METADATA = 'extra_metadata';
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'dir';
    public function path(): string;
    public function type(): string;
    public function visibility(): ?string;
    public function last_modified(): ?int;
    public static function from_array(array $attributes): Storage_Attributes;
    public function is_file(): bool;
    public function is_dir(): bool;
    public function with_path(string $path): Storage_Attributes;
    public function extra_metadata(): array;
}