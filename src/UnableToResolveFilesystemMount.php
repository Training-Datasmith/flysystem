<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
class Unable_To_Resolve_Filesystem_Mount extends RuntimeException implements Filesystem_Exception
{
    public static function because_the_separator_is_missing(string $path): Unable_To_Resolve_Filesystem_Mount
    {
        return new Unable_To_Resolve_Filesystem_Mount("Unable to resolve the filesystem mount because the path ({$path}) is missing a separator (://).");
    }
    public static function because_the_mount_was_not_registered(string $mount_identifier): Unable_To_Resolve_Filesystem_Mount
    {
        return new Unable_To_Resolve_Filesystem_Mount("Unable to resolve the filesystem mount because the mount ({$mount_identifier}) was not registered.");
    }
}