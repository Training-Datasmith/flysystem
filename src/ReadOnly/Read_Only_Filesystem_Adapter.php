<?php

declare (strict_types=1);
namespace League\Flysystem\Read_Only;

use DateTimeInterface;
use League\Flysystem\Calculate_Checksum_From_Stream;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Decorated_Adapter;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Public_Url;
use League\Flysystem\Unable_To_Generate_Temporary_Url;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
class Read_Only_Filesystem_Adapter extends Decorated_Adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    use Calculate_Checksum_From_Stream;
    public function write(string $path, string $contents, Config $config): void
    {
        throw Unable_To_Write_File::at_location($path, 'This is a readonly adapter.');
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        throw Unable_To_Write_File::at_location($path, 'This is a readonly adapter.');
    }
    public function delete(string $path): void
    {
        throw Unable_To_Delete_File::at_location($path, 'This is a readonly adapter.');
    }
    public function delete_directory(string $path): void
    {
        throw Unable_To_Delete_Directory::at_location($path, 'This is a readonly adapter.');
    }
    public function create_directory(string $path, Config $config): void
    {
        throw Unable_To_Create_Directory::at_location($path, 'This is a readonly adapter.');
    }
    public function set_visibility(string $path, string $visibility): void
    {
        throw Unable_To_Set_Visibility::at_location($path, 'This is a readonly adapter.');
    }
    public function move(string $source, string $destination, Config $config): void
    {
        throw new Unable_To_Move_File("Unable to move file from {$source} to {$destination} as this is a readonly adapter.");
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        throw new Unable_To_Copy_File("Unable to copy file from {$source} to {$destination} as this is a readonly adapter.");
    }
    public function public_url(string $path, Config $config): string
    {
        if (!$this->adapter instanceof Public_Url_Generator) {
            throw Unable_To_Generate_Public_Url::no_generator_configured($path);
        }
        return $this->adapter->public_url($path, $config);
    }
    public function checksum(string $path, Config $config): string
    {
        if ($this->adapter instanceof Checksum_Provider) {
            return $this->adapter->checksum($path, $config);
        }
        return $this->calculate_checksum_from_stream($path, $config);
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
    {
        if (!$this->adapter instanceof Temporary_Url_Generator) {
            throw Unable_To_Generate_Temporary_Url::no_generator_configured($path);
        }
        return $this->adapter->temporary_url($path, $expires_at, $config);
    }
}