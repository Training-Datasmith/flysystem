<?php

declare (strict_types=1);
namespace League\Flysystem\Path_Prefixing;

use DateTimeInterface;
use Generator;
use League\Flysystem\Calculate_Checksum_From_Stream;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Unable_To_Check_Directory_Existence;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Public_Url;
use League\Flysystem\Unable_To_Generate_Temporary_Url;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
use Throwable;
class Path_Prefixed_Adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    use Calculate_Checksum_From_Stream;
    private Path_Prefixer $prefix;
    public function __construct(private Filesystem_Adapter $adapter, string $prefix)
    {
        if ($prefix === '') {
            throw new \InvalidArgumentException('The prefix must not be empty.');
        }
        $this->prefix = new Path_Prefixer($prefix);
    }
    public function read(string $location): string
    {
        try {
            return $this->adapter->read($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Read_File::from_location($location, $previous->get_message(), $previous);
        }
    }
    public function read_stream(string $location)
    {
        try {
            return $this->adapter->read_stream($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Read_File::from_location($location, $previous->get_message(), $previous);
        }
    }
    public function list_contents(string $location, bool $deep): Generator
    {
        foreach ($this->adapter->list_contents($this->prefix->prefix_path($location), $deep) as $attributes) {
            yield $attributes->with_path($this->prefix->strip_prefix($attributes->path()));
        }
    }
    public function file_exists(string $location): bool
    {
        try {
            return $this->adapter->file_exists($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Check_File_Existence::for_location($location, $previous);
        }
    }
    public function directory_exists(string $location): bool
    {
        try {
            return $this->adapter->directory_exists($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Check_Directory_Existence::for_location($location, $previous);
        }
    }
    public function last_modified(string $path): File_Attributes
    {
        try {
            return $this->adapter->last_modified($this->prefix->prefix_path($path));
        } catch (Throwable $previous) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, $previous->get_message(), $previous);
        }
    }
    public function file_size(string $path): File_Attributes
    {
        try {
            return $this->adapter->file_size($this->prefix->prefix_path($path));
        } catch (Throwable $previous) {
            throw Unable_To_Retrieve_Metadata::file_size($path, $previous->get_message(), $previous);
        }
    }
    public function mime_type(string $path): File_Attributes
    {
        try {
            return $this->adapter->mime_type($this->prefix->prefix_path($path));
        } catch (Throwable $previous) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, $previous->get_message(), $previous);
        }
    }
    public function visibility(string $path): File_Attributes
    {
        try {
            return $this->adapter->visibility($this->prefix->prefix_path($path));
        } catch (Throwable $previous) {
            throw Unable_To_Retrieve_Metadata::visibility($path, $previous->get_message(), $previous);
        }
    }
    public function write(string $location, string $contents, Config $config): void
    {
        try {
            $this->adapter->write($this->prefix->prefix_path($location), $contents, $config);
        } catch (Throwable $previous) {
            throw Unable_To_Write_File::at_location($location, $previous->get_message(), $previous);
        }
    }
    public function write_stream(string $location, $contents, Config $config): void
    {
        try {
            $this->adapter->write_stream($this->prefix->prefix_path($location), $contents, $config);
        } catch (Throwable $previous) {
            throw Unable_To_Write_File::at_location($location, $previous->get_message(), $previous);
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        try {
            $this->adapter->set_visibility($this->prefix->prefix_path($path), $visibility);
        } catch (Throwable $previous) {
            throw Unable_To_Set_Visibility::at_location($path, $previous->get_message(), $previous);
        }
    }
    public function delete(string $location): void
    {
        try {
            $this->adapter->delete($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Delete_File::at_location($location, $previous->get_message(), $previous);
        }
    }
    public function delete_directory(string $location): void
    {
        try {
            $this->adapter->delete_directory($this->prefix->prefix_path($location));
        } catch (Throwable $previous) {
            throw Unable_To_Delete_Directory::at_location($location, $previous->get_message(), $previous);
        }
    }
    public function create_directory(string $location, Config $config): void
    {
        try {
            $this->adapter->create_directory($this->prefix->prefix_path($location), $config);
        } catch (Throwable $previous) {
            throw Unable_To_Create_Directory::at_location($location, $previous->get_message(), $previous);
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->adapter->move($this->prefix->prefix_path($source), $this->prefix->prefix_path($destination), $config);
        } catch (Throwable $previous) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $previous);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->adapter->copy($this->prefix->prefix_path($source), $this->prefix->prefix_path($destination), $config);
        } catch (Throwable $previous) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $previous);
        }
    }
    public function public_url(string $path, Config $config): string
    {
        if (!$this->adapter instanceof Public_Url_Generator) {
            throw Unable_To_Generate_Public_Url::no_generator_configured($path);
        }
        return $this->adapter->public_url($this->prefix->prefix_path($path), $config);
    }
    public function checksum(string $path, Config $config): string
    {
        if ($this->adapter instanceof Checksum_Provider) {
            return $this->adapter->checksum($this->prefix->prefix_path($path), $config);
        }
        return $this->calculate_checksum_from_stream($path, $config);
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
    {
        if (!$this->adapter instanceof Temporary_Url_Generator) {
            throw Unable_To_Generate_Temporary_Url::no_generator_configured($path);
        }
        return $this->adapter->temporary_url($this->prefix->prefix_path($path), $expires_at, $config);
    }
}