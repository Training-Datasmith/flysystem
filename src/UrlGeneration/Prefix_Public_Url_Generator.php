<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use League\Flysystem\Config;
use League\Flysystem\Path_Prefixer;
class Prefix_Public_Url_Generator implements Public_Url_Generator
{
    private Path_Prefixer $prefixer;
    public function __construct(string $url_prefix)
    {
        $this->prefixer = new Path_Prefixer($url_prefix, '/');
    }
    public function public_url(string $path, Config $config): string
    {
        return $this->prefixer->prefix_path($path);
    }
}