<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use League\Flysystem\Config;
use League\Flysystem\Unable_To_Generate_Public_Url;
interface Public_Url_Generator
{
    /**
     * @throws UnableToGeneratePublicUrl
     */
    public function public_url(string $path, Config $config): string;
}