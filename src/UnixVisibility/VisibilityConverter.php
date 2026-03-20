<?php

declare (strict_types=1);
namespace League\Flysystem\Unix_Visibility;

interface Visibility_Converter
{
    public function for_file(string $visibility): int;
    public function for_directory(string $visibility): int;
    public function inverse_for_file(int $visibility): string;
    public function inverse_for_directory(int $visibility): string;
    public function default_for_directories(): int;
}