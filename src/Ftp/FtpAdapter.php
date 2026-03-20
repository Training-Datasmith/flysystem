<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use function array_map;
use DateTime;
use function error_clear_last;
use function error_get_last;
use function ftp_chdir;
use function ftp_close;
use Generator;
use function is_string;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Storage_Attributes;
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
use Throwable;
class Ftp_Adapter implements Filesystem_Adapter
{
    private const SYSTEM_TYPE_WINDOWS = 'windows';
    private const SYSTEM_TYPE_UNIX = 'unix';
    private Connection_Provider $connection_provider;
    private Connectivity_Checker $connectivity_checker;
    /**
     * @var resource|false|\FTP\Connection
     */
    private mixed $connection = false;
    private Path_Prefixer $prefixer;
    private Visibility_Converter $visibility_converter;
    private ?bool $is_pure_ftpd_server = null;
    private ?bool $use_raw_list_options;
    private ?string $system_type;
    private Mime_Type_Detector $mime_type_detector;
    private ?string $root_directory = null;
    public function __construct(private Ftp_Connection_Options $connection_options, ?Connection_Provider $connection_provider = null, ?Connectivity_Checker $connectivity_checker = null, ?Visibility_Converter $visibility_converter = null, ?Mime_Type_Detector $mime_type_detector = null, private bool $detect_mime_type_using_path = false)
    {
        $this->system_type = $this->connection_options->system_type();
        $this->connection_provider = $connection_provider ?? new Ftp_Connection_Provider();
        $this->connectivity_checker = $connectivity_checker ?? new Noop_Command_Connectivity_Checker();
        $this->visibility_converter = $visibility_converter ?? new Portable_Visibility_Converter();
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
        $this->use_raw_list_options = $connection_options->use_raw_list_options();
    }
    /**
     * Disconnect FTP connection on destruct.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
    /**
     * @return resource
     */
    private function connection()
    {
        start:
        if (!$this->has_ftp_connection()) {
            $this->connection = $this->connection_provider->create_connection($this->connection_options);
            $this->root_directory = $this->resolve_connection_root($this->connection);
            $this->prefixer = new Path_Prefixer($this->root_directory);
            return $this->connection;
        }
        if ($this->connectivity_checker->is_connected($this->connection) === false) {
            $this->connection = false;
            goto start;
        }
        ftp_chdir($this->connection, $this->root_directory);
        return $this->connection;
    }
    public function disconnect(): void
    {
        if ($this->has_ftp_connection()) {
            @ftp_close($this->connection);
        }
        $this->connection = false;
    }
    private function is_pure_ftpd_server(): bool
    {
        if ($this->is_pure_ftpd_server !== null) {
            return $this->is_pure_ftpd_server;
        }
        $response = ftp_raw($this->connection, 'HELP');
        return $this->is_pure_ftpd_server = stripos(implode(' ', $response), 'Pure-FTPd') !== false;
    }
    private function is_server_supporting_list_options(): bool
    {
        if ($this->use_raw_list_options !== null) {
            return $this->use_raw_list_options;
        }
        $response = ftp_raw($this->connection, 'SYST');
        $syst = implode(' ', $response);
        return $this->use_raw_list_options = stripos($syst, 'FileZilla') === false && stripos($syst, 'L8') === false;
    }
    public function file_exists(string $path): bool
    {
        try {
            $this->file_size($path);
            return true;
        } catch (Unable_To_Retrieve_Metadata) {
            return false;
        }
    }
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $write_stream = fopen('php://temp', 'w+b');
            fwrite($write_stream, $contents);
            rewind($write_stream);
            $this->write_stream($path, $write_stream, $config);
        } finally {
            isset($write_stream) && is_resource($write_stream) && fclose($write_stream);
        }
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        try {
            $this->ensure_parent_directory_exists($path, $config->get(Config::OPTION_DIRECTORY_VISIBILITY));
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, 'creating parent directory failed', $exception);
        }
        $location = $this->prefixer()->prefix_path($path);
        if (!ftp_fput($this->connection(), $location, $contents, $this->connection_options->transfer_mode())) {
            throw Unable_To_Write_File::at_location($path, 'writing the file failed');
        }
        if (!$visibility = $config->get(Config::OPTION_VISIBILITY)) {
            return;
        }
        try {
            $this->set_visibility($path, $visibility);
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, 'setting visibility failed', $exception);
        }
    }
    public function read(string $path): string
    {
        $read_stream = $this->read_stream($path);
        $contents = stream_get_contents($read_stream);
        fclose($read_stream);
        return $contents;
    }
    public function read_stream(string $path)
    {
        $location = $this->prefixer()->prefix_path($path);
        $stream = fopen('php://temp', 'w+b');
        $result = @ftp_fget($this->connection(), $stream, $location, $this->connection_options->transfer_mode());
        if (!$result) {
            fclose($stream);
            throw Unable_To_Read_File::from_location($path, error_get_last()['message'] ?? '');
        }
        rewind($stream);
        return $stream;
    }
    public function delete(string $path): void
    {
        $connection = $this->connection();
        $this->delete_file($path, $connection);
    }
    /**
     * @param resource $connection
     */
    private function delete_file(string $path, $connection): void
    {
        $location = $this->prefixer()->prefix_path($path);
        $success = @ftp_delete($connection, $location);
        if ($success === false && ftp_size($connection, $location) !== -1) {
            throw Unable_To_Delete_File::at_location($path, 'the file still exists');
        }
    }
    public function delete_directory(string $path): void
    {
        /** @var StorageAttributes[] $contents */
        $contents = $this->list_contents($path, true);
        $connection = $this->connection();
        $directories = [$path];
        foreach ($contents as $item) {
            if ($item->is_dir()) {
                $directories[] = $item->path();
                continue;
            }
            try {
                $this->delete_file($item->path(), $connection);
            } catch (Throwable $exception) {
                throw Unable_To_Delete_Directory::at_location($path, 'unable to delete child', $exception);
            }
        }
        rsort($directories);
        foreach ($directories as $directory) {
            if (!@ftp_rmdir($connection, $this->prefixer()->prefix_path($directory))) {
                throw Unable_To_Delete_Directory::at_location($path, "Could not delete directory {$directory}");
            }
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $this->ensure_directory_exists($path, $config->get(Config::OPTION_DIRECTORY_VISIBILITY, $config->get(Config::OPTION_VISIBILITY)));
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $location = $this->prefixer()->prefix_path($path);
        $mode = $this->visibility_converter->for_file($visibility);
        if (!@ftp_chmod($this->connection(), $mode, $location)) {
            $message = error_get_last()['message'] ?? '';
            throw Unable_To_Set_Visibility::at_location($path, $message);
        }
    }
    private function fetch_metadata(string $path, string $type): File_Attributes
    {
        $location = $this->prefixer()->prefix_path($path);
        if ($this->is_pure_ftpd_server) {
            $location = $this->escape_path($location);
        }
        $object = @ftp_raw($this->connection(), 'STAT ' . $location);
        if (empty($object) || count($object) < 3 || str_starts_with($object[1], 'ftpd:')) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, error_get_last()['message'] ?? '');
        }
        $attributes = $this->normalize_object($object[1], '');
        if (!$attributes instanceof File_Attributes) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, 'expected file, ' . ($attributes instanceof Directory_Attributes ? 'directory found' : 'nothing found'));
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
        $location = $this->prefixer()->prefix_path($path);
        $connection = $this->connection();
        $last_modified = @ftp_mdtm($connection, $location);
        if ($last_modified < 0) {
            throw Unable_To_Retrieve_Metadata::last_modified($path);
        }
        return new File_Attributes($path, null, null, $last_modified);
    }
    public function visibility(string $path): File_Attributes
    {
        return $this->fetch_metadata($path, File_Attributes::ATTRIBUTE_VISIBILITY);
    }
    public function file_size(string $path): File_Attributes
    {
        $location = $this->prefixer()->prefix_path($path);
        $connection = $this->connection();
        $file_size = @ftp_size($connection, $location);
        if ($file_size < 0) {
            throw Unable_To_Retrieve_Metadata::file_size($path, error_get_last()['message'] ?? '');
        }
        return new File_Attributes($path, $file_size);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $path = ltrim($path, '/');
        $path = $path === '' ? $path : trim($path, '/') . '/';
        if ($deep && $this->connection_options->recurse_manually()) {
            yield from $this->list_directory_contents_recursive($path);
        } else {
            $location = $this->prefixer()->prefix_path($path);
            $options = $deep ? '-alnR' : '-aln';
            $listing = $this->ftp_rawlist($options, $location);
            yield from $this->normalize_listing($listing, $path);
        }
    }
    private function normalize_listing(array $listing, string $prefix = ''): Generator
    {
        $base = $prefix;
        foreach ($listing as $item) {
            if ($item === '') {
                continue;
            }
            if (preg_match('#.* \.(\.)?$|^total#', $item)) {
                continue;
            }
            if (preg_match('#^.*:$#', $item)) {
                $base = preg_replace('~^\./*|:$~', '', $item);
                continue;
            }
            yield $this->normalize_object($item, $base);
        }
    }
    private function normalize_object(string $item, string $base): Storage_Attributes
    {
        $this->system_type === null && $this->system_type = $this->detect_system_type($item);
        if ($this->system_type === self::SYSTEM_TYPE_UNIX) {
            return $this->normalize_unix_object($item, $base);
        }
        return $this->normalize_windows_object($item, $base);
    }
    private function detect_system_type(string $item): string
    {
        return preg_match('/^[0-9]{2,4}-[0-9]{2}-[0-9]{2}/', $item) ? self::SYSTEM_TYPE_WINDOWS : self::SYSTEM_TYPE_UNIX;
    }
    private function normalize_windows_object(string $item, string $base): Storage_Attributes
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 3);
        $parts = explode(' ', $item, 4);
        if (count($parts) !== 4) {
            throw new Invalid_List_Response_Received("Metadata can't be parsed from item '{$item}' , not enough parts.");
        }
        [$date, $time, $size, $name] = $parts;
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;
        if ($size === '<DIR>') {
            return new Directory_Attributes($path);
        }
        // Check for the correct date/time format
        $format = strlen($date) === 8 ? 'm-d-yH:iA' : 'Y-m-dH:i';
        $dt = DateTime::create_from_format($format, $date . $time);
        $last_modified = $dt ? $dt->get_timestamp() : (int) strtotime("{$date} {$time}");
        return new File_Attributes($path, (int) $size, null, $last_modified);
    }
    private function normalize_unix_object(string $item, string $base): Storage_Attributes
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 7);
        $parts = explode(' ', $item, 9);
        if (count($parts) !== 9) {
            throw new Invalid_List_Response_Received("Metadata can't be parsed from item '{$item}' , not enough parts.");
        }
        [$permissions, , , , $size, $month, $day, $time_or_year, $name] = $parts;
        $is_directory = $this->listing_item_is_directory($permissions);
        $permissions = $this->normalize_permissions($permissions);
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;
        $last_modified = $this->connection_options->timestamps_on_unix_listings_enabled() ? $this->normalize_unix_timestamp($month, $day, $time_or_year) : null;
        if ($is_directory) {
            return new Directory_Attributes($path, $this->visibility_converter->inverse_for_directory($permissions), $last_modified);
        }
        $visibility = $this->visibility_converter->inverse_for_file($permissions);
        return new File_Attributes($path, (int) $size, $visibility, $last_modified);
    }
    private function listing_item_is_directory(string $permissions): bool
    {
        return str_starts_with($permissions, 'd');
    }
    private function normalize_unix_timestamp(string $month, string $day, string $time_or_year): int
    {
        if (is_numeric($time_or_year)) {
            $year = $time_or_year;
            $hour = '00';
            $minute = '00';
        } else {
            $year = date('Y');
            [$hour, $minute] = explode(':', $time_or_year);
        }
        $date_time = DateTime::create_from_format('Y-M-j-G:i:s', "{$year}-{$month}-{$day}-{$hour}:{$minute}:00");
        return $date_time->get_timestamp();
    }
    private function normalize_permissions(string $permissions): int
    {
        // remove the type identifier
        $permissions = substr($permissions, 1);
        // map the string rights to the numeric counterparts
        $map = ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1'];
        $permissions = strtr($permissions, $map);
        // split up the permission groups
        $parts = str_split($permissions, 3);
        // convert the groups
        $mapper = static fn($part) => array_sum(array_map(static fn($p) => (int) $p, str_split($part)));
        // converts to decimal number
        return octdec(implode('', array_map($mapper, $parts)));
    }
    private function list_directory_contents_recursive(string $directory): Generator
    {
        $location = $this->prefixer()->prefix_path($directory);
        $listing = $this->ftp_rawlist('-aln', $location);
        /** @var StorageAttributes[] $listing */
        $listing = $this->normalize_listing($listing, $directory);
        foreach ($listing as $item) {
            yield $item;
            if (!$item->is_dir()) {
                continue;
            }
            $children = $this->list_directory_contents_recursive($item->path());
            foreach ($children as $child) {
                yield $child;
            }
        }
    }
    private function ftp_rawlist(string $options, string $path): array
    {
        $path = rtrim($path, '/') . '/';
        $connection = $this->connection();
        if ($this->is_pure_ftpd_server()) {
            $path = str_replace(' ', '\ ', $path);
            $path = $this->escape_path($path);
        }
        if (!$this->is_server_supporting_list_options()) {
            $options = '';
        }
        return ftp_rawlist($connection, ($options ? $options . ' ' : '') . $path, stripos($options, 'R') !== false) ?: [];
    }
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->ensure_parent_directory_exists($destination, $config->get(Config::OPTION_DIRECTORY_VISIBILITY));
        } catch (Throwable $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
        $source_location = $this->prefixer()->prefix_path($source);
        $destination_location = $this->prefixer()->prefix_path($destination);
        $connection = $this->connection();
        if (!@ftp_rename($connection, $source_location, $destination_location)) {
            throw Unable_To_Move_File::because(error_get_last()['message'] ?? 'reason unknown', $source, $destination);
        }
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
    private function ensure_parent_directory_exists(string $path, ?string $visibility): void
    {
        $dirname = dirname($path);
        if ($dirname === '' || $dirname === '.') {
            return;
        }
        $this->ensure_directory_exists($dirname, $visibility);
    }
    private function ensure_directory_exists(string $dirname, ?string $visibility): void
    {
        $connection = $this->connection();
        $dir_path = '';
        $parts = explode('/', trim($dirname, '/'));
        $mode = $visibility ? $this->visibility_converter->for_directory($visibility) : false;
        foreach ($parts as $part) {
            $dir_path .= '/' . $part;
            $location = $this->prefixer()->prefix_path($dir_path);
            if (@ftp_chdir($connection, $location)) {
                continue;
            }
            error_clear_last();
            $result = @ftp_mkdir($connection, $location);
            if ($result === false) {
                $error_message = error_get_last()['message'] ?? 'unable to create the directory';
                throw Unable_To_Create_Directory::at_location($dir_path, $error_message);
            }
            if ($mode !== false && @ftp_chmod($connection, $mode, $location) === false) {
                throw Unable_To_Create_Directory::at_location($dir_path, 'unable to chmod the directory: ' . (error_get_last()['message'] ?? 'reason unknown'));
            }
        }
    }
    private function escape_path(string $path): string
    {
        return str_replace(['*', '[', ']'], ['\*', '\[', '\]'], $path);
    }
    private function has_ftp_connection(): bool
    {
        return $this->connection instanceof \FTP\Connection || is_resource($this->connection);
    }
    public function directory_exists(string $path): bool
    {
        $location = $this->prefixer()->prefix_path($path);
        $connection = $this->connection();
        return @ftp_chdir($connection, $location) === true;
    }
    /**
     * @param resource|\FTP\Connection $connection
     */
    private function resolve_connection_root($connection): string
    {
        $root = $this->connection_options->root();
        error_clear_last();
        if ($root !== '' && @ftp_chdir($connection, $root) !== true) {
            throw Unable_To_Resolve_Connection_Root::it_does_not_exist($root, error_get_last()['message'] ?? '');
        }
        error_clear_last();
        $pwd = @ftp_pwd($connection);
        if (!is_string($pwd)) {
            throw Unable_To_Resolve_Connection_Root::could_not_get_current_directory(error_get_last()['message'] ?? '');
        }
        return $pwd;
    }
    private function prefixer(): Path_Prefixer
    {
        if ($this->root_directory === null) {
            $this->connection();
        }
        return $this->prefixer;
    }
}