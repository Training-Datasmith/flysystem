<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use League\Flysystem\Config;
use League\Flysystem\Unable_To_Generate_Public_Url;
final class Chained_Public_Url_Generator implements Public_Url_Generator
{
    /**
     * @param PublicUrlGenerator[] $generators
     */
    public function __construct(private iterable $generators)
    {
    }
    public function public_url(string $path, Config $config): string
    {
        foreach ($this->generators as $generator) {
            try {
                return $generator->public_url($path, $config);
            } catch (Unable_To_Generate_Public_Url) {
            }
        }
        throw new Unable_To_Generate_Public_Url('No supported public url generator found.', $path);
    }
}