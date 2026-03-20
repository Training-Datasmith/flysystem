<?php

declare (strict_types=1);
namespace League\Flysystem;

use function rtrim;
use function strlen;
use function substr;
final class Path_Prefixer
{
    private string $prefix = '';
    public function __construct(string $prefix, private string $separator = '/')
    {
        $this->prefix = rtrim($prefix, '\/');
        if ($this->prefix !== '' || $prefix === $separator) {
            $this->prefix .= $separator;
        }
    }
    public function prefix_path(string $path): string
    {
        return $this->prefix . ltrim($path, '\/');
    }
    public function strip_prefix(string $path): string
    {
        /* @var string */
        return substr($path, strlen($this->prefix));
    }
    public function strip_directory_prefix(string $path): string
    {
        return rtrim($this->strip_prefix($path), '\/');
    }
    public function prefix_directory_path(string $path): string
    {
        $prefixed_path = $this->prefix_path(rtrim($path, '\/'));
        if ($prefixed_path === '' || substr($prefixed_path, -1) === $this->separator) {
            return $prefixed_path;
        }
        return $prefixed_path . $this->separator;
    }
}