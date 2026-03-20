<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use function fclose;
use function fopen;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Unix_Visibility\Visibility_Converter;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use function rewind;
use function stream_copy_to_stream;
use Throwable;
use Zip_Archive;
final class Zip_Archive_Adapter implements Filesystem_Adapter
{
    private Path_Prefixer $path_prefixer;
    private Mime_Type_Detector $mime_type_detector;
    private Visibility_Converter $visibility;
    public function __construct(private Zip_Archive_Provider $zip_archive_provider, string $root = '', ?Mime_Type_Detector $mime_type_detector = null, ?Visibility_Converter $visibility = null, private bool $detect_mime_type_using_path = false)
    {
        $this->path_prefixer = new Path_Prefixer(ltrim($root, '/'));
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
        $this->visibility = $visibility ?? new Portable_Visibility_Converter();
    }
    public function file_exists(string $path): bool
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $file_exists = $archive->locate_name($this->path_prefixer->prefix_path($path)) !== false;
        $archive->close();
        return $file_exists;
    }
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->ensure_parent_directory_exists($path, $config);
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, 'creating parent directory failed', $exception);
        }
        $archive = $this->zip_archive_provider->create_zip_archive();
        $prefixed_path = $this->path_prefixer->prefix_path($path);
        if (!$archive->add_from_string($prefixed_path, $contents)) {
            throw Unable_To_Write_File::at_location($path, 'writing the file failed');
        }
        $archive->close();
        $archive = $this->zip_archive_provider->create_zip_archive();
        $visibility = $config->get(Config::OPTION_VISIBILITY);
        $visibility_result = $visibility === null || $this->set_visibility_attribute($prefixed_path, $visibility, $archive);
        $archive->close();
        if ($visibility_result === false) {
            throw Unable_To_Write_File::at_location($path, 'setting visibility failed');
        }
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $contents = stream_get_contents($contents);
        if ($contents === false) {
            throw Unable_To_Write_File::at_location($path, 'Could not get contents of given resource.');
        }
        $this->write($path, $contents, $config);
    }
    public function read(string $path): string
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $contents = $archive->get_from_name($this->path_prefixer->prefix_path($path));
        $status_string = $archive->get_status_string();
        $archive->close();
        if ($contents === false) {
            throw Unable_To_Read_File::from_location($path, $status_string);
        }
        return $contents;
    }
    public function read_stream(string $path)
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $resource = $archive->get_stream($this->path_prefixer->prefix_path($path));
        if ($resource === false) {
            $status = $archive->get_status_string();
            $archive->close();
            throw Unable_To_Read_File::from_location($path, $status);
        }
        $stream = fopen('php://temp', 'w+b');
        stream_copy_to_stream($resource, $stream);
        rewind($stream);
        fclose($resource);
        return $stream;
    }
    public function delete(string $path): void
    {
        $prefixed_path = $this->path_prefixer->prefix_path($path);
        $zip_archive = $this->zip_archive_provider->create_zip_archive();
        $success = $zip_archive->locate_name($prefixed_path) === false || $zip_archive->delete_name($prefixed_path);
        $status_string = $zip_archive->get_status_string();
        $zip_archive->close();
        if (!$success) {
            throw Unable_To_Delete_File::at_location($path, $status_string);
        }
    }
    public function delete_directory(string $path): void
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $prefixed_path = $this->path_prefixer->prefix_directory_path($path);
        for ($i = $archive->num_files; $i > 0; $i--) {
            if (($stats = $archive->stat_index($i)) === false) {
                continue;
            }
            $item_path = $stats['name'];
            if (!str_starts_with($item_path, $prefixed_path)) {
                continue;
            }
            if (!$archive->delete_index($i)) {
                $status_string = $archive->get_status_string();
                $archive->close();
                throw Unable_To_Delete_Directory::at_location($path, $status_string);
            }
        }
        $archive->delete_name($prefixed_path);
        $archive->close();
    }
    public function create_directory(string $path, Config $config): void
    {
        try {
            $this->ensure_directory_exists($path, $config);
        } catch (Throwable $exception) {
            throw Unable_To_Create_Directory::due_to_failure($path, $exception);
        }
    }
    public function directory_exists(string $path): bool
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $location = $this->path_prefixer->prefix_directory_path($path);
        return $archive->stat_name($location) !== false;
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $location = $this->path_prefixer->prefix_path($path);
        $stats = $archive->stat_name($location) ?: $archive->stat_name($location . '/');
        if ($stats === false) {
            $status_string = $archive->get_status_string();
            $archive->close();
            throw Unable_To_Set_Visibility::at_location($path, $status_string);
        }
        if (!$this->set_visibility_attribute($stats['name'], $visibility, $archive)) {
            $status_string1 = $archive->get_status_string();
            $archive->close();
            throw Unable_To_Set_Visibility::at_location($path, $status_string1);
        }
        $archive->close();
    }
    public function visibility(string $path): File_Attributes
    {
        $opsys = null;
        $attr = null;
        $archive = $this->zip_archive_provider->create_zip_archive();
        $archive->get_external_attributes_name($this->path_prefixer->prefix_path($path), $opsys, $attr);
        $archive->close();
        if ($opsys !== Zip_Archive::OPSYS_UNIX || $attr === null) {
            throw Unable_To_Retrieve_Metadata::visibility($path);
        }
        return new File_Attributes($path, null, $this->visibility->inverse_for_file($attr >> 16));
    }
    public function mime_type(string $path): File_Attributes
    {
        try {
            $mimetype = $this->detect_mime_type_using_path ? $this->mime_type_detector->detect_mime_type_from_path($path) : $this->mime_type_detector->detect_mime_type($path, $this->read($path));
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, $exception->get_message(), $exception);
        }
        if ($mimetype === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'Unknown.');
        }
        return new File_Attributes($path, null, null, null, $mimetype);
    }
    public function last_modified(string $path): File_Attributes
    {
        $zip_archive = $this->zip_archive_provider->create_zip_archive();
        $stats = $zip_archive->stat_name($this->path_prefixer->prefix_path($path));
        $status_string = $zip_archive->get_status_string();
        $zip_archive->close();
        if ($stats === false) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, $status_string);
        }
        return new File_Attributes($path, null, null, $stats['mtime']);
    }
    public function file_size(string $path): File_Attributes
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $stats = $archive->stat_name($this->path_prefixer->prefix_path($path));
        $status_string = $archive->get_status_string();
        $archive->close();
        if ($stats === false) {
            throw Unable_To_Retrieve_Metadata::file_size($path, $status_string);
        }
        if ($this->is_directory_path($stats['name'])) {
            throw Unable_To_Retrieve_Metadata::file_size($path, 'It\'s a directory.');
        }
        return new File_Attributes($path, $stats['size']);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $archive = $this->zip_archive_provider->create_zip_archive();
        $location = $this->path_prefixer->prefix_directory_path($path);
        $items = [];
        for ($i = 0; $i < $archive->num_files; $i++) {
            $stats = $archive->stat_index($i);
            // @codeCoverageIgnoreStart
            if ($stats === false) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $item_path = $stats['name'];
            if ($location === $item_path) {
                continue;
            }
            if ($deep && $location !== '' && !str_starts_with($item_path, $location)) {
                continue;
            }
            if ($deep === false && !$this->is_at_root_directory($location, $item_path)) {
                continue;
            }
            $items[] = $this->is_directory_path($item_path) ? new Directory_Attributes($this->path_prefixer->strip_directory_prefix($item_path), null, $stats['mtime']) : new File_Attributes($this->path_prefixer->strip_prefix($item_path), $stats['size'], null, $stats['mtime']);
        }
        $archive->close();
        return $this->yield_items_from($items);
    }
    private function yield_items_from(array $items): Generator
    {
        yield from $items;
    }
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->ensure_parent_directory_exists($destination, $config);
        } catch (Throwable $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
        $archive = $this->zip_archive_provider->create_zip_archive();
        if ($archive->locate_name($this->path_prefixer->prefix_path($destination)) !== false) {
            if ($source === $destination) {
                //update the config of the file
                $this->copy($source, $destination, $config);
                return;
            }
            $this->delete($destination);
            $this->copy($source, $destination, $config);
            $this->delete($source);
            return;
        }
        $renamed = $archive->rename_name($this->path_prefixer->prefix_path($source), $this->path_prefixer->prefix_path($destination));
        if ($renamed === false) {
            throw Unable_To_Move_File::from_location_to($source, $destination);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $read_stream = $this->read_stream($source);
            $this->write_stream($destination, $read_stream, $config);
        } catch (Throwable $exception) {
            if (isset($read_stream)) {
                @fclose($read_stream);
            }
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    private function ensure_parent_directory_exists(string $path, Config $config): void
    {
        $dirname = dirname($path);
        if ($dirname === '' || $dirname === '.') {
            return;
        }
        $this->ensure_directory_exists($dirname, $config);
    }
    private function ensure_directory_exists(string $dirname, Config $config): void
    {
        $visibility = $config->get(Config::OPTION_DIRECTORY_VISIBILITY);
        $archive = $this->zip_archive_provider->create_zip_archive();
        $prefixed_dirname = $this->path_prefixer->prefix_directory_path($dirname);
        $parts = array_filter(explode('/', trim($prefixed_dirname, '/')));
        $dir_path = '';
        foreach ($parts as $part) {
            $dir_path .= $part . '/';
            $info = $archive->stat_name($dir_path);
            if ($info === false && $archive->add_empty_dir($dir_path) === false) {
                throw Unable_To_Create_Directory::at_location($dirname);
            }
            if ($visibility === null) {
                continue;
            }
            if (!$this->set_visibility_attribute($dir_path, $visibility, $archive)) {
                $archive->close();
                throw Unable_To_Create_Directory::at_location($dirname, 'Unable to set visibility.');
            }
        }
        $archive->close();
    }
    private function is_directory_path(string $path): bool
    {
        return str_ends_with($path, '/');
    }
    private function is_at_root_directory(string $directory_root, string $path): bool
    {
        $dirname = dirname($path);
        if ('' === $directory_root && '.' === $dirname) {
            return true;
        }
        return $directory_root === rtrim($dirname, '/') . '/';
    }
    private function set_visibility_attribute(string $stats_name, string $visibility, Zip_Archive $archive): bool
    {
        $visibility = $this->is_directory_path($stats_name) ? $this->visibility->for_directory($visibility) : $this->visibility->for_file($visibility);
        return $archive->set_external_attributes_name($stats_name, Zip_Archive::OPSYS_UNIX, $visibility << 16);
    }
}