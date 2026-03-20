<?php

declare (strict_types=1);
namespace League\Flysystem\In_Memory;

class Static_In_Memory_Adapter_Registry
{
    /** @var array<string, InMemoryFilesystemAdapter> */
    private static array $filesystems = [];
    public static function get(string $name = 'default'): In_Memory_Filesystem_Adapter
    {
        return static::$filesystems[$name] ??= new In_Memory_Filesystem_Adapter();
    }
    public static function delete_all_filesystems(): void
    {
        self::$filesystems = [];
    }
}