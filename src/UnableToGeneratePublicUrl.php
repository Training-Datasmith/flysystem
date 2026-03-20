<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Generate_Public_Url extends RuntimeException implements Filesystem_Exception
{
    public function __construct(string $reason, string $path, ?Throwable $previous = null)
    {
        parent::__construct("Unable to generate public url for {$path}: {$reason}", 0, $previous);
    }
    public static function due_to_error(string $path, Throwable $exception): static
    {
        return new static($exception->get_message(), $path, $exception);
    }
    public static function no_generator_configured(string $path, string $extra_reason = ''): static
    {
        return new static('No generator was configured ' . $extra_reason, $path);
    }
}