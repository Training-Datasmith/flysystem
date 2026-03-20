<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use DateTimeInterface;
use League\Flysystem\Config;
use League\Flysystem\Unable_To_Generate_Temporary_Url;
interface Temporary_Url_Generator
{
    /**
     * @throws UnableToGenerateTemporaryUrl
     */
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string;
}