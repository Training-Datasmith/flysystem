<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use Zip_Archive;
class Filesystem_Zip_Archive_Provider implements Zip_Archive_Provider
{
    private bool $parent_directory_created = false;
    public function __construct(private string $filename, private int $local_directory_permissions = 0700)
    {
    }
    public function create_zip_archive(): Zip_Archive
    {
        if ($this->parent_directory_created !== true) {
            $this->parent_directory_created = true;
            $this->create_parent_directory_for_zip_archive($this->filename);
        }
        return $this->open_zip_archive();
    }
    private function create_parent_directory_for_zip_archive(string $full_path): void
    {
        $dirname = dirname($full_path);
        if (is_dir($dirname) || @mkdir($dirname, $this->local_directory_permissions, true)) {
            return;
        }
        if (!is_dir($dirname)) {
            throw Unable_To_Create_Parent_Directory::at_location($full_path, error_get_last()['message'] ?? '');
        }
    }
    private function open_zip_archive(): Zip_Archive
    {
        $archive = new Zip_Archive();
        $success = $archive->open($this->filename, Zip_Archive::CREATE);
        if ($success !== true) {
            throw Unable_To_Open_Zip_Archive::at_location($this->filename, $archive->get_status_string() ?: '');
        }
        return $archive;
    }
}