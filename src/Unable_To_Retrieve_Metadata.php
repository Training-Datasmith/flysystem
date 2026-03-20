<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Retrieve_Metadata extends RuntimeException implements Filesystem_Operation_Failed
{
    private ?string $location = null;
    private ?string $metadata_type = null;
    private ?string $reason = null;
    public static function last_modified(string $location, string $reason = '', ?Throwable $previous = null): self
    {
        return static::create($location, File_Attributes::ATTRIBUTE_LAST_MODIFIED, $reason, $previous);
    }
    public static function visibility(string $location, string $reason = '', ?Throwable $previous = null): self
    {
        return static::create($location, File_Attributes::ATTRIBUTE_VISIBILITY, $reason, $previous);
    }
    public static function file_size(string $location, string $reason = '', ?Throwable $previous = null): self
    {
        return static::create($location, File_Attributes::ATTRIBUTE_FILE_SIZE, $reason, $previous);
    }
    public static function mime_type(string $location, string $reason = '', ?Throwable $previous = null): self
    {
        return static::create($location, File_Attributes::ATTRIBUTE_MIME_TYPE, $reason, $previous);
    }
    public static function create(string $location, string $type, string $reason = '', ?Throwable $previous = null): self
    {
        $e = new static("Unable to retrieve the {$type} for file at location: {$location}. {$reason}", 0, $previous);
        $e->reason = $reason;
        $e->location = $location;
        $e->metadata_type = $type;
        return $e;
    }
    public function reason(): string
    {
        return $this->reason;
    }
    public function location(): string
    {
        return $this->location;
    }
    public function metadata_type(): string
    {
        return $this->metadata_type;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_RETRIEVE_METADATA;
    }
}