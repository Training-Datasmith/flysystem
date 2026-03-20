<?php

declare (strict_types=1);
namespace League\Flysystem;

class Whitespace_Path_Normalizer implements Path_Normalizer
{
    public function normalize_path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $this->reject_funky_white_space($path);
        return $this->normalize_relative_path($path);
    }
    private function reject_funky_white_space(string $path): void
    {
        if (preg_match('#\p{C}+#u', $path)) {
            throw Corrupted_Path_Detected::for_path($path);
        }
    }
    private function normalize_relative_path(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            switch ($part) {
                case '':
                case '.':
                    break;
                case '..':
                    if (empty($parts)) {
                        throw Path_Traversal_Detected::for_path($path);
                    }
                    array_pop($parts);
                    break;
                default:
                    $parts[] = $part;
                    break;
            }
        }
        return implode('/', $parts);
    }
}