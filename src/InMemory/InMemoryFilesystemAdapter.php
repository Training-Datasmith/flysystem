<?php

declare (strict_types=1);
namespace League\Flysystem\In_Memory;

use function array_keys;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Visibility;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use function rtrim;
class In_Memory_Filesystem_Adapter implements Filesystem_Adapter
{
    public const DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST = '______DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST';
    /**
     * @var InMemoryFile[]
     */
    private array $files = [];
    private Mime_Type_Detector $mime_type_detector;
    public function __construct(private string $default_visibility = Visibility::PUBLIC, ?Mime_Type_Detector $mime_type_detector = null)
    {
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function file_exists(string $path): bool
    {
        return array_key_exists($this->prepare_path($path), $this->files);
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $path = $this->prepare_path($path);
        $file = $this->files[$path] ??= new In_Memory_File();
        $file->update_contents($contents, $config->get('timestamp'));
        $visibility = $config->get(Config::OPTION_VISIBILITY, $this->default_visibility);
        $file->set_visibility($visibility);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }
    public function read(string $path): string
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Read_File::from_location($path, 'file does not exist');
        }
        return $this->files[$path]->read();
    }
    public function read_stream(string $path)
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Read_File::from_location($path, 'file does not exist');
        }
        return $this->files[$path]->read_stream();
    }
    public function delete(string $path): void
    {
        unset($this->files[$this->prepare_path($path)]);
    }
    public function delete_directory(string $path): void
    {
        $path = $this->prepare_path($path);
        $path = rtrim($path, '/') . '/';
        foreach (array_keys($this->files) as $file_path) {
            if (str_starts_with($file_path, $path)) {
                unset($this->files[$file_path]);
            }
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $file_path = rtrim($path, '/') . '/' . self::DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST;
        $this->write($file_path, '', $config);
    }
    public function directory_exists(string $path): bool
    {
        $path = $this->prepare_path($path);
        $path = rtrim($path, '/') . '/';
        foreach (array_keys($this->files) as $file_path) {
            if (str_starts_with($file_path, $path)) {
                return true;
            }
        }
        return false;
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Set_Visibility::at_location($path, 'file does not exist');
        }
        $this->files[$path]->set_visibility($visibility);
    }
    public function visibility(string $path): File_Attributes
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Retrieve_Metadata::visibility($path, 'file does not exist');
        }
        return new File_Attributes($path, null, $this->files[$path]->visibility());
    }
    public function mime_type(string $path): File_Attributes
    {
        $prepared_path = $this->prepare_path($path);
        if (array_key_exists($prepared_path, $this->files) === false) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'file does not exist');
        }
        $mime_type = $this->mime_type_detector->detect_mime_type($path, $this->files[$prepared_path]->read());
        if ($mime_type === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path);
        }
        return new File_Attributes($prepared_path, null, null, null, $mime_type);
    }
    public function last_modified(string $path): File_Attributes
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, 'file does not exist');
        }
        return new File_Attributes($path, null, null, $this->files[$path]->last_modified());
    }
    public function file_size(string $path): File_Attributes
    {
        $path = $this->prepare_path($path);
        if (array_key_exists($path, $this->files) === false) {
            throw Unable_To_Retrieve_Metadata::file_size($path, 'file does not exist');
        }
        return new File_Attributes($path, $this->files[$path]->file_size());
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $prefix = rtrim($this->prepare_path($path), '/') . '/';
        $prefix_length = strlen($prefix);
        $listed_directories = [];
        foreach ($this->files as $file_path => $file) {
            if (str_starts_with($file_path, $prefix)) {
                $sub_path = substr($file_path, $prefix_length);
                $dirname = dirname($sub_path);
                if ($dirname !== '.') {
                    $parts = explode('/', $dirname);
                    $dir_path = '';
                    foreach ($parts as $index => $part) {
                        if ($deep === false && $index >= 1) {
                            break;
                        }
                        $dir_path .= $part . '/';
                        if (!in_array($dir_path, $listed_directories, true)) {
                            $listed_directories[] = $dir_path;
                            yield new Directory_Attributes(trim($prefix . $dir_path, '/'));
                        }
                    }
                }
                $dummy_filename = self::DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST;
                if (str_ends_with($file_path, $dummy_filename)) {
                    continue;
                }
                if ($deep === true || !str_contains($sub_path, '/')) {
                    yield new File_Attributes(ltrim($file_path, '/'), $file->file_size(), $file->visibility(), $file->last_modified(), $file->mime_type());
                }
            }
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        $source_path = $this->prepare_path($source);
        $destination_path = $this->prepare_path($destination);
        if (!$this->file_exists($source)) {
            throw Unable_To_Move_File::from_location_to($source, $destination);
        }
        if ($source_path !== $destination_path) {
            $this->files[$destination_path] = $this->files[$source_path];
            unset($this->files[$source_path]);
        }
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->set_visibility($destination, $visibility);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $source = $this->prepare_path($source);
        $destination = $this->prepare_path($destination);
        if (!$this->file_exists($source)) {
            throw Unable_To_Copy_File::from_location_to($source, $destination);
        }
        $last_modified = $config->get('timestamp', time());
        $this->files[$destination] = $this->files[$source]->with_last_modified($last_modified);
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->set_visibility($destination, $visibility);
        }
    }
    private function prepare_path(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
    public function delete_everything(): void
    {
        $this->files = [];
    }
}