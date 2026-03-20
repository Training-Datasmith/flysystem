<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use Zip_Archive;
interface Zip_Archive_Provider
{
    public function create_zip_archive(): Zip_Archive;
}