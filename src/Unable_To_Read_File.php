<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Read_File extends RuntimeException implements Filesystem_Operation_Failed
{
    private string $location = '';
    private string $reason = '';
    public static function from_location(string $location, string $reason = '', ?Throwable $previous = null): Unable_To_Read_File
    {
        $e = new static(rtrim("Unable to read file from location: {$location}. {$reason}"), 0, $previous);
        $e->location = $location;
        $e->reason = $reason;
        return $e;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_READ;
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