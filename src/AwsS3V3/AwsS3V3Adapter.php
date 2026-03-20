<?php

declare (strict_types=1);
namespace League\Flysystem\Aws_S3v3;

use Aws\Api\Date_Time_Result;
use Aws\S3\S3client_Interface;
use DateTimeInterface;
use Generator;
use League\Flysystem\Checksum_Algo_Is_Not_Supported;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Filesystem_Operation_Failed;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Check_Directory_Existence;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Public_Url;
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
use Psr\Http\Message\Stream_Interface;
use Throwable;
use function trim;
class Aws_S3v3adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    /**
     * @var string[]
     */
    public const AVAILABLE_OPTIONS = ['ACL', 'CacheControl', 'ContentDisposition', 'ContentEncoding', 'ContentLength', 'ContentType', 'Expires', 'GrantFullControl', 'GrantRead', 'GrantReadACP', 'GrantWriteACP', 'Metadata', 'MetadataDirective', 'RequestPayer', 'SSECustomerAlgorithm', 'SSECustomerKey', 'SSECustomerKeyMD5', 'SSEKMSKeyId', 'ServerSideEncryption', 'StorageClass', 'Tagging', 'WebsiteRedirectLocation', 'ChecksumAlgorithm', 'CopySourceSSECustomerAlgorithm', 'CopySourceSSECustomerKey', 'CopySourceSSECustomerKeyMD5'];
    /**
     * @var string[]
     */
    public const MUP_AVAILABLE_OPTIONS = ['add_content_md5', 'before_upload', 'concurrency', 'mup_threshold', 'params', 'part_size'];
    /**
     * @var string[]
     */
    private const EXTRA_METADATA_FIELDS = ['Metadata', 'StorageClass', 'ETag', 'VersionId'];
    private Path_Prefixer $prefixer;
    private Visibility_Converter $visibility;
    private Mime_Type_Detector $mime_type_detector;
    public function __construct(private S3client_Interface $client, private string $bucket, string $prefix = '', ?Visibility_Converter $visibility = null, ?Mime_Type_Detector $mime_type_detector = null, private array $options = [], private bool $stream_reads = true, private array $forwarded_options = self::AVAILABLE_OPTIONS, private array $metadata_fields = self::EXTRA_METADATA_FIELDS, private array $multipart_upload_options = self::MUP_AVAILABLE_OPTIONS)
    {
        $this->prefixer = new Path_Prefixer($prefix);
        $this->visibility = $visibility ?? new Portable_Visibility_Converter();
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function file_exists(string $path): bool
    {
        try {
            return $this->client->does_object_exist_v2($this->bucket, $this->prefixer->prefix_path($path), false, $this->options);
        } catch (Throwable $exception) {
            throw Unable_To_Check_File_Existence::for_location($path, $exception);
        }
    }
    public function directory_exists(string $path): bool
    {
        try {
            $prefix = $this->prefixer->prefix_directory_path($path);
            $options = ['Bucket' => $this->bucket, 'Prefix' => $prefix, 'MaxKeys' => 1, 'Delimiter' => '/'];
            $command = $this->client->get_command('ListObjectsV2', $options);
            $result = $this->client->execute($command);
            if ($result->has_key('Contents')) {
                return true;
            }
            return (bool) $result->has_key('CommonPrefixes');
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    /**
     * @param string|resource $body
     */
    private function upload(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefix_path($path);
        $options = $this->create_options_from_config($config);
        $acl = $options['params']['ACL'] ?? $this->determine_acl($config);
        $should_determine_mimetype = !array_key_exists('ContentType', $options['params']);
        if ($should_determine_mimetype && $mime_type = $this->mime_type_detector->detect_mime_type($key, $body)) {
            $options['params']['ContentType'] = $mime_type;
        }
        try {
            $this->client->upload($this->bucket, $key, $body, $acl, $options);
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    private function determine_acl(Config $config): string
    {
        $visibility = (string) $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
        return $this->visibility->visibility_to_acl($visibility);
    }
    private function create_options_from_config(Config $config): array
    {
        $config = $config->with_defaults($this->options);
        $options = ['params' => []];
        if ($mimetype = $config->get('mimetype')) {
            $options['params']['ContentType'] = $mimetype;
        }
        foreach ($this->forwarded_options as $option) {
            $value = $config->get($option, '__NOT_SET__');
            if ($value !== '__NOT_SET__') {
                $options['params'][$option] = $value;
            }
        }
        foreach ($this->multipart_upload_options as $option) {
            $value = $config->get($option, '__NOT_SET__');
            if ($value !== '__NOT_SET__') {
                $options[$option] = $value;
            }
        }
        return $options;
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    public function read(string $path): string
    {
        $body = $this->read_object($path, false);
        return (string) $body->get_contents();
    }
    public function read_stream(string $path)
    {
        /** @var resource $resource */
        $resource = $this->read_object($path, true)->detach();
        return $resource;
    }
    public function delete(string $path): void
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        $command = $this->client->get_command('DeleteObject', $arguments);
        try {
            $this->client->execute($command);
        } catch (Throwable $exception) {
            throw Unable_To_Delete_File::at_location($path, '', $exception);
        }
    }
    public function delete_directory(string $path): void
    {
        $prefix = $this->prefixer->prefix_path($path);
        $prefix = ltrim(rtrim($prefix, '/') . '/', '/');
        try {
            $this->client->delete_matching_objects($this->bucket, $prefix);
        } catch (Throwable $exception) {
            throw Unable_To_Delete_Directory::at_location($path, '', $exception);
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $default_visibility = $config->get(Config::OPTION_DIRECTORY_VISIBILITY, $this->visibility->default_for_directories());
        $config = $config->with_defaults([Config::OPTION_VISIBILITY => $default_visibility]);
        $this->upload(rtrim($path, '/') . '/', '', $config);
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path), 'ACL' => $this->visibility->visibility_to_acl($visibility)];
        $command = $this->client->get_command('PutObjectAcl', $arguments);
        try {
            $this->client->execute($command);
        } catch (Throwable $exception) {
            throw Unable_To_Set_Visibility::at_location($path, '', $exception);
        }
    }
    public function visibility(string $path): File_Attributes
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        $command = $this->client->get_command('GetObjectAcl', $arguments);
        try {
            $result = $this->client->execute($command);
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::visibility($path, '', $exception);
        }
        $visibility = $this->visibility->acl_to_visibility((array) $result->get('Grants'));
        return new File_Attributes($path, null, $visibility);
    }
    private function fetch_file_metadata(string $path, string $type): File_Attributes
    {
        $options = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        $command = $this->client->get_command('HeadObject', $options + $this->options);
        try {
            $result = $this->client->execute($command);
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, '', $exception);
        }
        $attributes = $this->map_s3object_metadata($result->to_array(), $path);
        if (!$attributes instanceof File_Attributes) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, '');
        }
        return $attributes;
    }
    private function map_s3object_metadata(array $metadata, string $path): Storage_Attributes
    {
        if (str_ends_with($path, '/')) {
            return new Directory_Attributes(rtrim($path, '/'));
        }
        $mimetype = $metadata['ContentType'] ?? null;
        $file_size = $metadata['ContentLength'] ?? $metadata['Size'] ?? null;
        $file_size = $file_size === null ? null : (int) $file_size;
        $date_time = $metadata['LastModified'] ?? null;
        $last_modified = $date_time instanceof Date_Time_Result ? $date_time->get_time_stamp() : null;
        return new File_Attributes($path, $file_size, null, $last_modified, $mimetype, $this->extract_extra_metadata($metadata));
    }
    private function extract_extra_metadata(array $metadata): array
    {
        $extracted = [];
        foreach ($this->metadata_fields as $field) {
            if (isset($metadata[$field]) && $metadata[$field] !== '') {
                $extracted[$field] = $metadata[$field];
            }
        }
        return $extracted;
    }
    public function mime_type(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_MIME_TYPE);
        if ($attributes->mime_type() === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path);
        }
        return $attributes;
    }
    public function last_modified(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_LAST_MODIFIED);
        if ($attributes->last_modified() === null) {
            throw Unable_To_Retrieve_Metadata::last_modified($path);
        }
        return $attributes;
    }
    public function file_size(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_FILE_SIZE);
        if ($attributes->file_size() === null) {
            throw Unable_To_Retrieve_Metadata::file_size($path);
        }
        return $attributes;
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $prefix = trim($this->prefixer->prefix_path($path), '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';
        $options = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        if ($deep === false) {
            $options['Delimiter'] = '/';
        }
        $listing = $this->retrieve_paginated_listing($options);
        foreach ($listing as $item) {
            $key = $item['Key'] ?? $item['Prefix'];
            if ($key === $prefix) {
                continue;
            }
            yield $this->map_s3object_metadata($item, $this->prefixer->strip_prefix($key));
        }
    }
    private function retrieve_paginated_listing(array $options): Generator
    {
        $result_paginator = $this->client->get_paginator('ListObjectsV2', $options + $this->options);
        foreach ($result_paginator as $result) {
            yield from $result->get('CommonPrefixes') ?? [];
            yield from $result->get('Contents') ?? [];
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Filesystem_Operation_Failed $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }
        try {
            $visibility = $config->get(Config::OPTION_VISIBILITY);
            if ($visibility === null && $config->get(Config::OPTION_RETAIN_VISIBILITY, true)) {
                $visibility = $this->visibility($source)->visibility();
            }
        } catch (Throwable $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
        $options = $this->create_options_from_config($config);
        $options['MetadataDirective'] = $config->get('MetadataDirective', 'COPY');
        try {
            $this->client->copy($this->bucket, $this->prefixer->prefix_path($source), $this->bucket, $this->prefixer->prefix_path($destination), $this->visibility->visibility_to_acl($visibility ?: 'private'), $options);
        } catch (Throwable $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    private function read_object(string $path, bool $wants_stream): Stream_Interface
    {
        $options = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        if ($wants_stream && $this->stream_reads && !isset($this->options['@http']['stream'])) {
            $options['@http']['stream'] = true;
        }
        $command = $this->client->get_command('GetObject', $options + $this->options);
        try {
            return $this->client->execute($command)->get('Body');
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, '', $exception);
        }
    }
    public function public_url(string $path, Config $config): string
    {
        $location = $this->prefixer->prefix_path($path);
        try {
            return $this->client->get_object_url($this->bucket, $location);
        } catch (Throwable $exception) {
            throw Unable_To_Generate_Public_Url::due_to_error($path, $exception);
        }
    }
    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'etag');
        if ($algo !== 'etag') {
            throw new Checksum_Algo_Is_Not_Supported();
        }
        try {
            $metadata = $this->fetch_file_metadata($path, 'checksum')->extra_metadata();
        } catch (Unable_To_Retrieve_Metadata $exception) {
            throw new Unable_To_Provide_Checksum($exception->reason(), $path, $exception);
        }
        if (!isset($metadata['ETag'])) {
            throw new Unable_To_Provide_Checksum('ETag header not available.', $path);
        }
        return trim($metadata['ETag'], '"');
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
    {
        try {
            $options = $config->get('get_object_options', []);
            $command = $this->client->get_command('GetObject', ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)] + $options);
            $presigned_request_options = $config->get('presigned_request_options', []);
            $request = $this->client->create_presigned_request($command, $expires_at, $presigned_request_options);
            return (string) $request->get_uri();
        } catch (Throwable $exception) {
            throw Unable_To_Generate_Temporary_Url::due_to_error($path, $exception);
        }
    }
}