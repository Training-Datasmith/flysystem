<?php

declare (strict_types=1);
namespace League\Flysystem\Local;

use function chmod;
use function clearstatcache;
use const DIRECTORY_SEPARATOR;
use Directory_Iterator;
use function dirname;
use function error_clear_last;
use function error_get_last;
use function file_exists;
use function file_put_contents;
use Filesystem_Iterator;
use Generator;
use function hash_file;
use function is_dir;
use function is_file;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Symbolic_Link_Encountered;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Provide_Checksum;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Unix_Visibility\Visibility_Converter;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use const LOCK_EX;
use function mkdir;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use function rename;
use Spl_File_Info;
use Throwable;
class Local_Filesystem_Adapter implements Filesystem_Adapter, Checksum_Provider
{
    /**
     * @var int
     */
    public const SKIP_LINKS = 01;
    /**
     * @var int
     */
    public const DISALLOW_LINKS = 02;
    private Path_Prefixer $prefixer;
    private Visibility_Converter $visibility;
    private Mime_Type_Detector $mime_type_detector;
    private string $root_location;
    private bool $root_location_is_setup = false;
    public function __construct(string $location, ?Visibility_Converter $visibility = null, private int $write_flags = LOCK_EX, private int $link_handling = self::DISALLOW_LINKS, ?Mime_Type_Detector $mime_type_detector = null, bool $lazy_root_creation = false, bool $use_inconclusive_mime_type_fallback = false)
    {
        $this->prefixer = new Path_Prefixer($location, DIRECTORY_SEPARATOR);
        $visibility ??= new Portable_Visibility_Converter();
        $this->visibility = $visibility;
        $this->root_location = $location;
        $this->mime_type_detector = $mime_type_detector ?? new Fallback_Mime_Type_Detector(detector: new Finfo_Mime_Type_Detector(), useInconclusiveMimeTypeFallback: $use_inconclusive_mime_type_fallback);
        if (!$lazy_root_creation) {
            $this->ensure_root_directory_exists();
        }
    }
    private function ensure_root_directory_exists(): void
    {
        if ($this->root_location_is_setup) {
            return;
        }
        $this->ensure_directory_exists($this->root_location, $this->visibility->default_for_directories());
        $this->root_location_is_setup = true;
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->write_to_file($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->write_to_file($path, $contents, $config);
    }
    /**
     * @param resource|string $contents
     */
    private function write_to_file(string $path, $contents, Config $config): void
    {
        $prefixed_location = $this->prefixer->prefix_path($path);
        $this->ensure_root_directory_exists();
        $this->ensure_directory_exists(dirname($prefixed_location), $this->resolve_directory_visibility($config->get(Config::OPTION_DIRECTORY_VISIBILITY)));
        error_clear_last();
        if (@file_put_contents($prefixed_location, $contents, $this->write_flags) === false) {
            throw Unable_To_Write_File::at_location($path, error_get_last()['message'] ?? '');
        }
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->set_visibility($path, (string) $visibility);
        }
    }
    public function delete(string $path): void
    {
        $location = $this->prefixer->prefix_path($path);
        if (!file_exists($location)) {
            return;
        }
        error_clear_last();
        if (!@unlink($location)) {
            throw Unable_To_Delete_File::at_location($location, error_get_last()['message'] ?? '');
        }
    }
    public function delete_directory(string $prefix): void
    {
        $location = $this->prefixer->prefix_path($prefix);
        if (!is_dir($location)) {
            return;
        }
        $contents = $this->list_directory_recursively($location, Recursive_Iterator_Iterator::CHILD_FIRST);
        /** @var SplFileInfo $file */
        foreach ($contents as $file) {
            if (!$this->delete_file_info_object($file)) {
                throw Unable_To_Delete_Directory::at_location($prefix, 'Unable to delete file at ' . $file->get_pathname());
            }
        }
        unset($contents);
        if (!@rmdir($location)) {
            throw Unable_To_Delete_Directory::at_location($prefix, error_get_last()['message'] ?? '');
        }
    }
    private function list_directory_recursively(string $path, int $mode = Recursive_Iterator_Iterator::SELF_FIRST): Generator
    {
        if (!is_dir($path)) {
            return;
        }
        yield from new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($path, Filesystem_Iterator::SKIP_DOTS), $mode);
    }
    protected function delete_file_info_object(Spl_File_Info $file): bool
    {
        return match ($file->get_type()) {
            'dir' => @rmdir((string) $file->get_real_path()),
            'link' => @unlink($file->get_pathname()),
            default => @unlink((string) $file->get_real_path()),
        };
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $location = $this->prefixer->prefix_path($path);
        if (!is_dir($location)) {
            return;
        }
        /** @var SplFileInfo[] $iterator */
        $iterator = $deep ? $this->list_directory_recursively($location) : $this->list_directory($location);
        foreach ($iterator as $file_info) {
            $path_name = $file_info->get_pathname();
            try {
                if ($file_info->is_link()) {
                    if ($this->link_handling & self::SKIP_LINKS) {
                        continue;
                    }
                    throw Symbolic_Link_Encountered::at_location($path_name);
                }
                $path = $this->prefixer->strip_prefix($path_name);
                $last_modified = $file_info->get_m_time();
                $is_directory = $file_info->is_dir();
                $permissions = octdec(substr(sprintf('%o', $file_info->get_perms()), -4));
                $visibility = $is_directory ? $this->visibility->inverse_for_directory($permissions) : $this->visibility->inverse_for_file($permissions);
                yield $is_directory ? new Directory_Attributes(str_replace('\\', '/', $path), $visibility, $last_modified) : new File_Attributes(str_replace('\\', '/', $path), $file_info->get_size(), $visibility, $last_modified);
            } catch (Throwable $exception) {
                if (file_exists($path_name)) {
                    throw $exception;
                }
            }
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        $source_path = $this->prefixer->prefix_path($source);
        $destination_path = $this->prefixer->prefix_path($destination);
        $this->ensure_root_directory_exists();
        $this->ensure_directory_exists(dirname($destination_path), $this->resolve_directory_visibility($config->get(Config::OPTION_DIRECTORY_VISIBILITY)));
        error_clear_last();
        if (!@rename($source_path, $destination_path)) {
            throw Unable_To_Move_File::because(error_get_last()['message'] ?? 'unknown reason', $source, $destination);
        }
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->set_visibility($destination, (string) $visibility);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $source_path = $this->prefixer->prefix_path($source);
        $destination_path = $this->prefixer->prefix_path($destination);
        $this->ensure_root_directory_exists();
        $this->ensure_directory_exists(dirname($destination_path), $this->resolve_directory_visibility($config->get(Config::OPTION_DIRECTORY_VISIBILITY)));
        error_clear_last();
        if ($source_path !== $destination_path && !@copy($source_path, $destination_path)) {
            throw Unable_To_Copy_File::because(error_get_last()['message'] ?? 'unknown', $source, $destination);
        }
        $visibility = $config->get(Config::OPTION_VISIBILITY, $config->get(Config::OPTION_RETAIN_VISIBILITY, true) ? $this->visibility($source)->visibility() : null);
        if ($visibility) {
            $this->set_visibility($destination, (string) $visibility);
        }
    }
    public function read(string $path): string
    {
        $location = $this->prefixer->prefix_path($path);
        error_clear_last();
        $contents = @file_get_contents($location);
        if ($contents === false) {
            throw Unable_To_Read_File::from_location($path, error_get_last()['message'] ?? '');
        }
        return $contents;
    }
    public function read_stream(string $path)
    {
        $location = $this->prefixer->prefix_path($path);
        error_clear_last();
        $contents = @fopen($location, 'rb');
        if ($contents === false) {
            throw Unable_To_Read_File::from_location($path, error_get_last()['message'] ?? '');
        }
        return $contents;
    }
    protected function ensure_directory_exists(string $dirname, int $visibility): void
    {
        if (is_dir($dirname)) {
            return;
        }
        error_clear_last();
        if (!@mkdir($dirname, $visibility, true)) {
            $mkdir_error = error_get_last();
        }
        clearstatcache(true, $dirname);
        if (!is_dir($dirname)) {
            $error_message = $mkdir_error['message'] ?? '';
            throw Unable_To_Create_Directory::at_location($dirname, $error_message);
        }
    }
    public function file_exists(string $location): bool
    {
        $location = $this->prefixer->prefix_path($location);
        clearstatcache();
        return is_file($location);
    }
    public function directory_exists(string $location): bool
    {
        $location = $this->prefixer->prefix_path($location);
        clearstatcache();
        return is_dir($location);
    }
    public function create_directory(string $path, Config $config): void
    {
        $this->ensure_root_directory_exists();
        $location = $this->prefixer->prefix_path($path);
        $visibility = $config->get(Config::OPTION_VISIBILITY, $config->get(Config::OPTION_DIRECTORY_VISIBILITY));
        $permissions = $this->resolve_directory_visibility($visibility);
        if (is_dir($location)) {
            $this->set_permissions($location, $permissions);
            return;
        }
        error_clear_last();
        if (!@mkdir($location, $permissions, true)) {
            throw Unable_To_Create_Directory::at_location($path, error_get_last()['message'] ?? '');
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $path = $this->prefixer->prefix_path($path);
        $visibility = is_dir($path) ? $this->visibility->for_directory($visibility) : $this->visibility->for_file($visibility);
        $this->set_permissions($path, $visibility);
    }
    public function visibility(string $path): File_Attributes
    {
        $location = $this->prefixer->prefix_path($path);
        clearstatcache(false, $location);
        error_clear_last();
        $fileperms = @fileperms($location);
        if ($fileperms === false) {
            throw Unable_To_Retrieve_Metadata::visibility($path, error_get_last()['message'] ?? '');
        }
        $permissions = $fileperms & 0777;
        $visibility = $this->visibility->inverse_for_file($permissions);
        return new File_Attributes($path, null, $visibility);
    }
    private function resolve_directory_visibility(?string $visibility): int
    {
        return $visibility === null ? $this->visibility->default_for_directories() : $this->visibility->for_directory($visibility);
    }
    public function mime_type(string $path): File_Attributes
    {
        $location = $this->prefixer->prefix_path($path);
        error_clear_last();
        if (!is_file($location)) {
            throw Unable_To_Retrieve_Metadata::mime_type($location, 'No such file exists.');
        }
        $mime_type = $this->mime_type_detector->detect_mime_type_from_file($location);
        if ($mime_type === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, error_get_last()['message'] ?? '');
        }
        return new File_Attributes($path, null, null, null, $mime_type);
    }
    public function last_modified(string $path): File_Attributes
    {
        $location = $this->prefixer->prefix_path($path);
        clearstatcache();
        error_clear_last();
        $last_modified = @filemtime($location);
        if ($last_modified === false) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, error_get_last()['message'] ?? '');
        }
        return new File_Attributes($path, null, null, $last_modified);
    }
    public function file_size(string $path): File_Attributes
    {
        $location = $this->prefixer->prefix_path($path);
        clearstatcache();
        error_clear_last();
        if (is_file($location) && ($file_size = @filesize($location)) !== false) {
            return new File_Attributes($path, $file_size);
        }
        throw Unable_To_Retrieve_Metadata::file_size($path, error_get_last()['message'] ?? '');
    }
    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'md5');
        $location = $this->prefixer->prefix_path($path);
        error_clear_last();
        $checksum = @hash_file($algo, $location);
        if ($checksum === false) {
            throw new Unable_To_Provide_Checksum(error_get_last()['message'] ?? '', $path);
        }
        return $checksum;
    }
    private function list_directory(string $location): Generator
    {
        $iterator = new Directory_Iterator($location);
        foreach ($iterator as $item) {
            if ($item->is_dot()) {
                continue;
            }
            yield $item;
        }
    }
    private function set_permissions(string $location, int $visibility): void
    {
        error_clear_last();
        if (!@chmod($location, $visibility)) {
            $extra_message = error_get_last()['message'] ?? '';
            throw Unable_To_Set_Visibility::at_location($this->prefixer->strip_prefix($location), $extra_message);
        }
    }
}