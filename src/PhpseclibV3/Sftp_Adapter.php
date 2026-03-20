<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Filesystem_Exception;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Check_Directory_Existence;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Unix_Visibility\Visibility_Converter;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use phpseclib3\Net\SFTP;
use function rtrim;
use Throwable;
class Sftp_Adapter implements Filesystem_Adapter
{
    private Visibility_Converter $visibility_converter;
    private Path_Prefixer $prefixer;
    private Mime_Type_Detector $mime_type_detector;
    public function __construct(private Connection_Provider $connection_provider, string $root, ?Visibility_Converter $visibility_converter = null, ?Mime_Type_Detector $mime_type_detector = null, private bool $detect_mime_type_using_path = false, private bool $disconnect_on_destruct = false)
    {
        $this->prefixer = new Path_Prefixer($root);
        $this->visibility_converter = $visibility_converter ?? new Portable_Visibility_Converter();
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function file_exists(string $path): bool
    {
        $location = $this->prefixer->prefix_path($path);
        try {
            return $this->connection_provider->provide_connection()->is_file($location);
        } catch (Throwable $exception) {
            throw Unable_To_Check_File_Existence::for_location($path, $exception);
        }
    }
    public function disconnect(): void
    {
        $this->connection_provider->disconnect();
    }
    public function directory_exists(string $path): bool
    {
        $location = $this->prefixer->prefix_directory_path($path);
        try {
            return $this->connection_provider->provide_connection()->is_dir($location);
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
    }
    /**
     * @param string|resource $contents
     *
     * @throws FilesystemException
     */
    private function upload(string $path, $contents, Config $config): void
    {
        $this->ensure_parent_directory_exists($path, $config);
        $connection = $this->connection_provider->provide_connection();
        $location = $this->prefixer->prefix_path($path);
        if (!$connection->put($location, $contents, SFTP::SOURCE_STRING)) {
            throw Unable_To_Write_File::at_location($path, 'not able to write the file');
        }
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->set_visibility($path, $visibility);
        }
    }
    private function ensure_parent_directory_exists(string $path, Config $config): void
    {
        $parent_directory = dirname($path);
        if ($parent_directory === '' || $parent_directory === '.') {
            return;
        }
        /** @var string $visibility */
        $visibility = $config->get(Config::OPTION_DIRECTORY_VISIBILITY);
        $this->make_directory($parent_directory, $visibility);
    }
    private function make_directory(string $directory, ?string $visibility): void
    {
        $location = $this->prefixer->prefix_path($directory);
        $connection = $this->connection_provider->provide_connection();
        if ($connection->is_dir($location)) {
            return;
        }
        $mode = $visibility ? $this->visibility_converter->for_directory($visibility) : $this->visibility_converter->default_for_directories();
        if (!$connection->mkdir($location, $mode, true) && !$connection->is_dir($location)) {
            throw Unable_To_Create_Directory::at_location($directory);
        }
    }
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (Unable_To_Write_File $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (Unable_To_Write_File $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function read(string $path): string
    {
        $location = $this->prefixer->prefix_path($path);
        $connection = $this->connection_provider->provide_connection();
        $contents = $connection->get($location);
        if (!is_string($contents)) {
            throw Unable_To_Read_File::from_location($path);
        }
        return $contents;
    }
    public function read_stream(string $path)
    {
        $location = $this->prefixer->prefix_path($path);
        $connection = $this->connection_provider->provide_connection();
        /** @var resource $readStream */
        $read_stream = fopen('php://temp', 'w+');
        if (!$connection->get($location, $read_stream)) {
            fclose($read_stream);
            throw Unable_To_Read_File::from_location($path);
        }
        rewind($read_stream);
        return $read_stream;
    }
    public function delete(string $path): void
    {
        $location = $this->prefixer->prefix_path($path);
        $connection = $this->connection_provider->provide_connection();
        $connection->delete($location);
    }
    public function delete_directory(string $path): void
    {
        $location = rtrim($this->prefixer->prefix_path($path), '/') . '/';
        $connection = $this->connection_provider->provide_connection();
        $connection->delete($location);
        $connection->rmdir($location);
    }
    public function create_directory(string $path, Config $config): void
    {
        $this->make_directory($path, $config->get(Config::OPTION_DIRECTORY_VISIBILITY, $config->get(Config::OPTION_VISIBILITY)));
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $location = $this->prefixer->prefix_path($path);
        $connection = $this->connection_provider->provide_connection();
        $mode = $this->visibility_converter->for_file($visibility);
        if (!$connection->chmod($mode, $location, false)) {
            throw Unable_To_Set_Visibility::at_location($path);
        }
    }
    private function fetch_file_metadata(string $path, string $type): File_Attributes
    {
        $location = $this->prefixer->prefix_path($path);
        $connection = $this->connection_provider->provide_connection();
        $stat = $connection->stat($location);
        if (!is_array($stat)) {
            throw Unable_To_Retrieve_Metadata::create($path, $type);
        }
        $attributes = $this->convert_listing_to_attributes($path, $stat);
        if (!$attributes instanceof File_Attributes) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, 'path is not a file');
        }
        return $attributes;
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
        return $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_LAST_MODIFIED);
    }
    public function file_size(string $path): File_Attributes
    {
        return $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_FILE_SIZE);
    }
    public function visibility(string $path): File_Attributes
    {
        return $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_VISIBILITY);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $connection = $this->connection_provider->provide_connection();
        $location = $this->prefixer->prefix_path(rtrim($path, '/')) . '/';
        $listing = $connection->rawlist($location, false);
        if (false === $listing) {
            return;
        }
        foreach ($listing as $filename => $attributes) {
            if ($filename === '.') {
                continue;
            }
            if ($filename === '..') {
                continue;
            }
            // Ensure numeric keys are strings.
            $filename = (string) $filename;
            $path = $this->prefixer->strip_prefix($location . ltrim($filename, '/'));
            $attributes = $this->convert_listing_to_attributes($path, $attributes);
            yield $attributes;
            if ($deep && $attributes->is_dir()) {
                foreach ($this->list_contents($attributes->path(), true) as $child) {
                    yield $child;
                }
            }
        }
    }
    private function convert_listing_to_attributes(string $path, array $attributes): Storage_Attributes
    {
        $permissions = $attributes['mode'] & 0777;
        $last_modified = $attributes['mtime'] ?? null;
        if (($attributes['type'] ?? null) === NET_SFTP_TYPE_DIRECTORY) {
            return new Directory_Attributes(ltrim($path, '/'), $this->visibility_converter->inverse_for_directory($permissions), $last_modified);
        }
        return new File_Attributes($path, $attributes['size'], $this->visibility_converter->inverse_for_file($permissions), $last_modified);
    }
    public function move(string $source, string $destination, Config $config): void
    {
        $source_location = $this->prefixer->prefix_path($source);
        $destination_location = $this->prefixer->prefix_path($destination);
        $connection = $this->connection_provider->provide_connection();
        try {
            $this->ensure_parent_directory_exists($destination, $config);
        } catch (Throwable $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
        if ($source_location === $destination_location) {
            return;
        }
        if ($connection->rename($source_location, $destination_location)) {
            return;
        }
        // Overwrite existing file / dir
        if ($connection->is_file($destination_location)) {
            $this->delete($destination);
            if ($connection->rename($source_location, $destination_location)) {
                return;
            }
        }
        throw Unable_To_Move_File::from_location_to($source, $destination);
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $read_stream = $this->read_stream($source);
            $visibility = $config->get(Config::OPTION_VISIBILITY);
            if ($visibility === null && $config->get(Config::OPTION_RETAIN_VISIBILITY, true)) {
                $config = $config->with_setting(Config::OPTION_VISIBILITY, $this->visibility($source)->visibility());
            }
            $this->write_stream($destination, $read_stream, $config);
        } catch (Throwable $exception) {
            if (isset($read_stream) && is_resource($read_stream)) {
                @fclose($read_stream);
            }
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    public function __destruct()
    {
        if ($this->disconnect_on_destruct) {
            $this->connection_provider->disconnect();
        }
    }
}