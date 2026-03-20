<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Write_File extends RuntimeException implements Filesystem_Operation_Failed
{
    private string $location = '';
    private ?string $reason = null;
    public static function at_location(string $location, string $reason = '', ?Throwable $previous = null): Unable_To_Write_File
    {
        $e = new static(rtrim("Unable to write file at location: {$location}. {$reason}"), 0, $previous);
        $e->location = $location;
        $e->reason = $reason;
        return $e;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_WRITE;
    }
    public function reason(): string
    {
        return $this->reason;
    }
    public function location(): string
    {
        return $this->location;
    }
}