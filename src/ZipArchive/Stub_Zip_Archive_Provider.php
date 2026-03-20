<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use Zip_Archive;
class Stub_Zip_Archive_Provider implements Zip_Archive_Provider
{
    private ?\League\Flysystem\Zip_Archive\Stub_Zip_Archive $archive = null;
    public function __construct(private string $filename)
    {
    }
    public function create_zip_archive(): Zip_Archive
    {
        $this->archive->open($this->filename, Zip_Archive::CREATE);
        return $this->archive;
    }
    public function stubbed_zip_archive(): Stub_Zip_Archive
    {
        $this->create_zip_archive();
        return $this->archive;
    }
}