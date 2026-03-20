<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
final class Symbolic_Link_Encountered extends RuntimeException implements Filesystem_Exception
{
    private string $location;
    public function location(): string
    {
        return $this->location;
    }
    public static function at_location(string $path_name): Symbolic_Link_Encountered
    {
        $e = new static("Unsupported symbolic link encountered at location {$path_name}");
        $e->location = $path_name;
        return $e;
    }
}