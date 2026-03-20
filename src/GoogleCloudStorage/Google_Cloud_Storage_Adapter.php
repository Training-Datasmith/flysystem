<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use function array_key_exists;
use function base64_decode;
use function bin2hex;
use function count;
use DateTimeInterface;
use Google\Cloud\Core\Exception\Not_Found_Exception;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\Storage_Object;
use League\Flysystem\Checksum_Algo_Is_Not_Supported;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Check_Directory_Existence;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Temporary_Url;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Provide_Checksum;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
use League\Flysystem\Visibility;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use LogicException;
use function rtrim;
use function sprintf;
use function strlen;
use Throwable;
class Google_Cloud_Storage_Adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    private Path_Prefixer $prefixer;
    private Visibility_Handler $visibility_handler;
    private Mime_Type_Detector $mime_type_detector;
    private static array $algo_to_info_map = ['md5' => 'md5Hash', 'crc32c' => 'crc32c', 'etag' => 'etag'];
    public function __construct(private Bucket $bucket, string $prefix = '', ?Visibility_Handler $visibility_handler = null, private string $default_visibility = Visibility::PRIVATE, ?Mime_Type_Detector $mime_type_detector = null, private bool $stream_reads = false)
    {
        $this->prefixer = new Path_Prefixer($prefix);
        $this->visibility_handler = $visibility_handler ?? new Portable_Visibility_Handler();
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function public_url(string $path, Config $config): string
    {
        $location = $this->prefixer->prefix_path($path);
        return 'https://storage.googleapis.com/' . $this->bucket->name() . '/' . ltrim($location, '/');
    }
    public function file_exists(string $path): bool
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        try {
            return $this->bucket->object($prefixed_path)->exists();
        } catch (Throwable $exception) {
            throw Unable_To_Check_File_Existence::for_location($path, $exception);
        }
    }
    public function directory_exists(string $path): bool
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        $options = ['delimiter' => '/', 'includeTrailingDelimiter' => true];
        if (strlen($prefixed_path) > 0) {
            $options = ['prefix' => rtrim($prefixed_path, '/') . '/'];
        }
        try {
            $objects = $this->bucket->objects($options);
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
        if (count($objects->prefixes()) > 0) {
            return true;
        }
        /** @var StorageObject $object */
        foreach ($objects as $object) {
            return true;
        }
        return false;
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    /**
     * @param resource|string $contents
     */
    private function upload(string $path, $contents, Config $config): void
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        $options = ['name' => $prefixed_path];
        $visibility = $config->get(Config::OPTION_VISIBILITY, $this->default_visibility);
        $predefined_acl = $this->visibility_handler->visibility_to_predefined_acl($visibility);
        if ($predefined_acl !== Portable_Visibility_Handler::NO_PREDEFINED_VISIBILITY) {
            $options['predefinedAcl'] = $predefined_acl;
        }
        $metadata = $config->get('metadata', []);
        $should_determine_mimetype = $contents !== '' && !array_key_exists('contentType', $metadata);
        if ($should_determine_mimetype && $mime_type = $this->mime_type_detector->detect_mime_type($path, $contents)) {
            $metadata['contentType'] = $mime_type;
        }
        $options['metadata'] = $metadata;
        try {
            $this->bucket->upload($contents, $options);
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function read(string $path): string
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        try {
            return $this->bucket->object($prefixed_path)->download_as_string();
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    public function read_stream(string $path)
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        $options = [];
        if ($this->stream_reads) {
            $options['restOptions']['stream'] = true;
        }
        try {
            $stream = $this->bucket->object($prefixed_path)->download_as_stream($options)->detach();
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
        // @codeCoverageIgnoreStart
        if (!is_resource($stream)) {
            throw Unable_To_Read_File::from_location($path, 'Downloaded object does not contain a file resource.');
        }
        // @codeCoverageIgnoreEnd
        return $stream;
    }
    public function delete(string $path): void
    {
        try {
            $prefixed_path = $this->prefixer->prefix_path($path);
            $this->bucket->object($prefixed_path)->delete();
        } catch (Not_Found_Exception) {
            // this is ok
        } catch (Throwable $exception) {
            throw Unable_To_Delete_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function delete_directory(string $path): void
    {
        try {
            /** @var StorageAttributes[] $listing */
            $listing = $this->list_contents($path, true);
            foreach ($listing as $attributes) {
                $this->delete($attributes->path());
            }
            if ($path !== '') {
                $this->delete(rtrim($path, '/') . '/');
            }
        } catch (Throwable $exception) {
            throw Unable_To_Delete_Directory::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $prefixed_path = $this->prefixer->prefix_directory_path($path);
        if ($prefixed_path !== '') {
            $this->bucket->upload('', ['name' => $prefixed_path]);
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        try {
            $prefixed_path = $this->prefixer->prefix_path($path);
            $object = $this->bucket->object($prefixed_path);
            $this->visibility_handler->set_visibility($object, $visibility);
        } catch (Throwable $previous) {
            throw Unable_To_Set_Visibility::at_location($path, $previous->get_message(), $previous);
        }
    }
    public function visibility(string $path): File_Attributes
    {
        try {
            $prefixed_path = $this->prefixer->prefix_path($path);
            $object = $this->bucket->object($prefixed_path);
            $visibility = $this->visibility_handler->determine_visibility($object);
            return new File_Attributes($path, null, $visibility);
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::visibility($path, $exception->get_message(), $exception);
        }
    }
    public function mime_type(string $path): File_Attributes
    {
        return $this->file_attributes($path, 'mimeType');
    }
    public function last_modified(string $path): File_Attributes
    {
        return $this->file_attributes($path, 'lastModified');
    }
    public function file_size(string $path): File_Attributes
    {
        return $this->file_attributes($path, 'fileSize');
    }
    private function file_attributes(string $path, string $type): File_Attributes
    {
        $exception = null;
        $prefixed_path = $this->prefixer->prefix_path($path);
        try {
            $object = $this->bucket->object($prefixed_path);
            $file_attributes = $this->storage_object_to_storage_attributes($object);
        } catch (Throwable $exception) {
            // passthrough
        }
        if (!isset($file_attributes) || !$file_attributes instanceof File_Attributes || $file_attributes[$type] === null) {
            throw Unable_To_Retrieve_Metadata::$type($path, isset($exception) ? $exception->get_message() : '', $exception);
        }
        return $file_attributes;
    }
    public function storage_object_to_storage_attributes(Storage_Object $object): Storage_Attributes
    {
        $path = $this->prefixer->strip_prefix($object->name());
        $info = $object->info();
        $last_modified = strtotime($info['updated']);
        if (str_ends_with($path, '/')) {
            return new Directory_Attributes(rtrim($path, '/'), null, $last_modified);
        }
        $file_size = intval($info['size']);
        $mime_type = $info['contentType'] ?? null;
        return new File_Attributes($path, $file_size, null, $last_modified, $mime_type, $info);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $prefixed_path = $this->prefixer->prefix_path($path);
        $prefixes = $options = [];
        if ($prefixed_path !== '') {
            $options = ['prefix' => sprintf('%s/', rtrim($prefixed_path, '/'))];
        }
        if ($deep === false) {
            $options['delimiter'] = '/';
            $options['includeTrailingDelimiter'] = true;
        }
        $objects = $this->bucket->objects($options);
        /** @var StorageObject $object */
        foreach ($objects as $object) {
            $prefixes[$this->prefixer->strip_directory_prefix($object->name())] = true;
            yield $this->storage_object_to_storage_attributes($object);
        }
        foreach ($objects->prefixes() as $prefix) {
            $prefix = $this->prefixer->strip_directory_prefix($prefix);
            if (array_key_exists($prefix, $prefixes)) {
                continue;
            }
            $prefixes[$prefix] = true;
            yield new Directory_Attributes($prefix);
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $visibility = $config->get(Config::OPTION_VISIBILITY);
            if ($visibility === null && $config->get(Config::OPTION_RETAIN_VISIBILITY, true)) {
                $visibility = $this->visibility($source)->visibility();
            }
            $prefixed_source = $this->prefixer->prefix_path($source);
            $options = ['name' => $this->prefixer->prefix_path($destination)];
            $predefined_acl = $this->visibility_handler->visibility_to_predefined_acl($visibility ?: Portable_Visibility_Handler::NO_PREDEFINED_VISIBILITY);
            if ($predefined_acl !== Portable_Visibility_Handler::NO_PREDEFINED_VISIBILITY) {
                $options['predefinedAcl'] = $predefined_acl;
            }
            $this->bucket->object($prefixed_source)->copy($this->bucket, $options);
        } catch (Throwable $previous) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $previous);
        }
    }
    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'md5');
        $header = static::$algo_to_info_map[$algo] ?? null;
        if ($header === null) {
            throw new Checksum_Algo_Is_Not_Supported();
        }
        $prefixed_path = $this->prefixer->prefix_path($path);
        try {
            $checksum = $this->bucket->object($prefixed_path)->info()[$header] ?? throw new LogicException("Header not present: {$header}");
        } catch (Throwable $exception) {
            throw new Unable_To_Provide_Checksum($exception->get_message(), $path);
        }
        return bin2hex(base64_decode($checksum));
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
    {
        $location = $this->prefixer->prefix_path($path);
        try {
            return $this->bucket->object($location)->signed_url($expires_at, $config->get('gcp_signing_options', []));
        } catch (Throwable $exception) {
            throw Unable_To_Generate_Temporary_Url::due_to_error($path, $exception);
        }
    }
}