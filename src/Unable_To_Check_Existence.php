<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
class Unable_To_Check_Existence extends RuntimeException implements Filesystem_Operation_Failed
{
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    public static function for_location(string $path, ?Throwable $exception = null): static
    {
        return new static("Unable to check existence for: {$path}", 0, $exception);
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_EXISTENCE_CHECK;
    }
}