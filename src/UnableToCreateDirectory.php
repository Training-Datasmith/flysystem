<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Create_Directory extends RuntimeException implements Filesystem_Operation_Failed
{
    private string $location;
    private string $reason = '';
    public static function at_location(string $dirname, string $error_message = '', ?Throwable $previous = null): Unable_To_Create_Directory
    {
        $message = "Unable to create a directory at {$dirname}. {$error_message}";
        $e = new static(rtrim($message), 0, $previous);
        $e->location = $dirname;
        $e->reason = $error_message;
        return $e;
    }
    public static function due_to_failure(string $dirname, Throwable $previous): Unable_To_Create_Directory
    {
        $reason = $previous instanceof Unable_To_Create_Directory ? $previous->reason() : '';
        $message = "Unable to create a directory at {$dirname}. {$reason}";
        $e = new static(rtrim($message), 0, $previous);
        $e->location = $dirname;
        $e->reason = $reason ?: $message;
        return $e;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_CREATE_DIRECTORY;
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