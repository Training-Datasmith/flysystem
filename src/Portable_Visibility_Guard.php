<?php

declare (strict_types=1);
namespace League\Flysystem;

final class Portable_Visibility_Guard
{
    public static function guard_against_invalid_input(string $visibility): void
    {
        if ($visibility !== Visibility::PUBLIC && $visibility !== Visibility::PRIVATE) {
            $class_name = Visibility::class;
            throw Invalid_Visibility_Provided::with_visibility($visibility, "either {$class_name}::PUBLIC or {$class_name}::PRIVATE");
        }
    }
}