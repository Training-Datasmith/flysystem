<?php

declare (strict_types=1);
namespace League\Flysystem;

interface Path_Normalizer
{
    public function normalize_path(string $path): string;
}