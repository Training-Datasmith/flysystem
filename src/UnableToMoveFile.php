<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_Move_File extends RuntimeException implements Filesystem_Operation_Failed
{
    private ?string $source = null;
    private ?string $destination = null;
    public static function source_and_destination_are_the_same(string $source, string $destination): Unable_To_Move_File
    {
        return Unable_To_Move_File::because('Source and destination are the same', $source, $destination);
    }
    public function source(): string
    {
        return $this->source;
    }
    public function destination(): string
    {
        return $this->destination;
    }
    public static function from_location_to(string $source_path, string $destination_path, ?Throwable $previous = null): Unable_To_Move_File
    {
        $message = $previous?->get_message() ?? "Unable to move file from {$source_path} to {$destination_path}";
        $e = new static($message, 0, $previous);
        $e->source = $source_path;
        $e->destination = $destination_path;
        return $e;
    }
    public static function because(string $reason, string $source_path, string $destination_path): Unable_To_Move_File
    {
        $message = "Unable to move file from {$source_path} to {$destination_path}, because {$reason}";
        $e = new static($message);
        $e->source = $source_path;
        $e->destination = $destination_path;
        return $e;
    }
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_MOVE;
    }
}