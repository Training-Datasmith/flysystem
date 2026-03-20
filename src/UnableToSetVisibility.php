<?php

declare (strict_types=1);
namespace League\Flysystem;

use function rtrim;
use RuntimeException;
use Throwable;
final class Unable_To_Set_Visibility extends RuntimeException implements Filesystem_Operation_Failed
{
    private ?string $location = null;
    private ?string $reason = null;
    public function reason(): string
    {
        return $this->reason;
    }
    public static function at_location(string $filename, string $extra_message = '', ?Throwable $previous = null): self
    {
        $message = "Unable to set visibility for file {$filename}. {$extra_message}";
        $e = new static(rtrim($message), 0, $previous);
        $e->reason = $extra_message;
        $e->location = $filename;
        return $e;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_SET_VISIBILITY;
    }
    public function location(): string
    {
        return $this->location;
    }
}