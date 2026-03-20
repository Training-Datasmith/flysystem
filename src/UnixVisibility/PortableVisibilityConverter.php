<?php

declare (strict_types=1);
namespace League\Flysystem\Unix_Visibility;

use League\Flysystem\Portable_Visibility_Guard;
use League\Flysystem\Visibility;
class Portable_Visibility_Converter implements Visibility_Converter
{
    public function __construct(private int $file_public = 0644, private int $file_private = 0600, private int $directory_public = 0755, private int $directory_private = 0700, private string $default_for_directories = Visibility::PRIVATE)
    {
    }
    public function for_file(string $visibility): int
    {
        Portable_Visibility_Guard::guard_against_invalid_input($visibility);
        return $visibility === Visibility::PUBLIC ? $this->file_public : $this->file_private;
    }
    public function for_directory(string $visibility): int
    {
        Portable_Visibility_Guard::guard_against_invalid_input($visibility);
        return $visibility === Visibility::PUBLIC ? $this->directory_public : $this->directory_private;
    }
    public function inverse_for_file(int $visibility): string
    {
        if ($visibility === $this->file_public) {
            return Visibility::PUBLIC;
        }
        if ($visibility === $this->file_private) {
            return Visibility::PRIVATE;
        }
        return Visibility::PUBLIC;
        // default
    }
    public function inverse_for_directory(int $visibility): string
    {
        if ($visibility === $this->directory_public) {
            return Visibility::PUBLIC;
        }
        if ($visibility === $this->directory_private) {
            return Visibility::PRIVATE;
        }
        return Visibility::PUBLIC;
        // default
    }
    public function default_for_directories(): int
    {
        return $this->default_for_directories === Visibility::PUBLIC ? $this->directory_public : $this->directory_private;
    }
    /**
     * @param array<mixed>  $permissionMap
     */
    public static function from_array(array $permission_map, string $default_for_directories = Visibility::PRIVATE): Portable_Visibility_Converter
    {
        return new Portable_Visibility_Converter($permission_map['file']['public'] ?? 0644, $permission_map['file']['private'] ?? 0600, $permission_map['dir']['public'] ?? 0755, $permission_map['dir']['private'] ?? 0700, $default_for_directories);
    }
}