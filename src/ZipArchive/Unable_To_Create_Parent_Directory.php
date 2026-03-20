<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use RuntimeException;
class Unable_To_Create_Parent_Directory extends RuntimeException implements Zip_Archive_Exception
{
    public static function at_location(string $location, string $reason = ''): Unable_To_Create_Parent_Directory
    {
        return new Unable_To_Create_Parent_Directory(rtrim("Unable to create the parent directory ({$location}): {$reason}", ' :'));
    }
}