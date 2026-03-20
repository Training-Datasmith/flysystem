<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
final class Corrupted_Path_Detected extends RuntimeException implements Filesystem_Exception
{
    public static function for_path(string $path): Corrupted_Path_Detected
    {
        return new Corrupted_Path_Detected('Corrupted path detected: ' . $path);
    }
}