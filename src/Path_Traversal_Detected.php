<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
class Path_Traversal_Detected extends RuntimeException implements Filesystem_Exception
{
    private string $path;
    public function path(): string
    {
        return $this->path;
    }
    public static function for_path(string $path): Path_Traversal_Detected
    {
        $e = new Path_Traversal_Detected("Path traversal detected: {$path}");
        $e->path = $path;
        return $e;
    }
}