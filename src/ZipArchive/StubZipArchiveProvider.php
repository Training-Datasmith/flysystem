<?php

declare(strict_types=1);

namespace League\Flysystem\ZipArchive;

use ZipArchive;

class StubZipArchiveProvider implements ZipArchiveProvider
{
    private ?\League\Flysystem\ZipArchive\StubZipArchive $archive = null;

    public function __construct(private string $filename)
    {
    }

    public function createZipArchive(): ZipArchive
    {
        $this->archive->open($this->filename, ZipArchive::CREATE);

        return $this->archive;
    }

    public function stubbedZipArchive(): StubZipArchive
    {
        $this->createZipArchive();

        return $this->archive;
    }
}
