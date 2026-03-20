<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use function array_map;
use function count;
use function crc32;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\Path_Prefixer;
final class Sharded_Prefix_Public_Url_Generator implements Public_Url_Generator
{
    /** @var PathPrefixer[] */
    private array $prefixes;
    private int $count;
    /**
     * @param string[] $prefixes
     */
    public function __construct(array $prefixes)
    {
        $this->count = count($prefixes);
        if ($this->count === 0) {
            throw new InvalidArgumentException('At least one prefix is required.');
        }
        $this->prefixes = array_map(static fn(string $prefix): \League\Flysystem\Path_Prefixer => new Path_Prefixer($prefix, '/'), $prefixes);
    }
    public function public_url(string $path, Config $config): string
    {
        $index = abs(crc32($path)) % $this->count;
        return $this->prefixes[$index]->prefix_path($path);
    }
}