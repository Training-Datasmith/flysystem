<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
final class Unreadable_File_Encountered extends RuntimeException implements Filesystem_Exception
{
    private ?string $location = null;
    public function location(): string
    {
        return $this->location;
    }
    public static function at_location(string $location): Unreadable_File_Encountered
    {
        $e = new static("Unreadable file encountered at location {$location}.");
        $e->location = $location;
        return $e;
    }
}