<?php

declare (strict_types=1);
namespace League\Flysystem\Async_Aws_S3;

use Async_Aws\Core\Exception\Http\Client_Exception;
use Async_Aws\Core\Stream\Result_Stream;
use Async_Aws\S3\Input\Get_Object_Request;
use Async_Aws\S3\Result\Head_Object_Output;
use Async_Aws\S3\S3Client;
use Async_Aws\S3\Value_Object\Aws_Object;
use Async_Aws\S3\Value_Object\Common_Prefix;
use Async_Aws\S3\Value_Object\Object_Identifier;
use Async_Aws\Simple_S3\Simple_S3client;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
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
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Public_Url;
use League\Flysystem\Unable_To_Generate_Temporary_Url;
use League\Flysystem\Unable_To_List_Contents;
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
use Throwable;
use function trim;
class Async_Aws_S3adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    /**
     * @var string[]
     */
    public const AVAILABLE_OPTIONS = ['ACL', 'CacheControl', 'ContentDisposition', 'ContentEncoding', 'ContentLength', 'ContentType', 'ContentMD5', 'Expires', 'GrantFullControl', 'GrantRead', 'GrantReadACP', 'GrantWriteACP', 'Metadata', 'MetadataDirective', 'RequestPayer', 'SSECustomerAlgorithm', 'SSECustomerKey', 'SSECustomerKeyMD5', 'SSEKMSKeyId', 'ServerSideEncryption', 'StorageClass', 'Tagging', 'WebsiteRedirectLocation', 'ChecksumAlgorithm', 'CopySourceSSECustomerAlgorithm', 'CopySourceSSECustomerKey', 'CopySourceSSECustomerKeyMD5'];
    /**
     * @var string[]
     */
    protected const EXTRA_METADATA_FIELDS = ['Metadata', 'StorageClass', 'ETag', 'VersionId'];
    private Path_Prefixer $prefixer;
    private Visibility_Converter $visibility;
    private Mime_Type_Detector $mime_type_detector;
    /**
     * @param S3Client|SimpleS3Client $client Uploading of files larger than 5GB is only supported with SimpleS3Client
     */
    public function __construct(
        private S3Client $client,
        private string $bucket,
        string $prefix = '',
        ?Visibility_Converter $visibility = null,
        ?Mime_Type_Detector $mime_type_detector = null,
        /**
         * @var array|string[]
         */
        private array $forwarded_options = self::AVAILABLE_OPTIONS,
        /**
         * @var array|string[]
         */
        private array $metadata_fields = self::EXTRA_METADATA_FIELDS
    )
    {
        $this->prefixer = new Path_Prefixer($prefix);
        $this->visibility = $visibility ?? new Portable_Visibility_Converter();
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function file_exists(string $path): bool
    {
        try {
            return $this->client->object_exists(['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)])->is_success();
        } catch (Client_Exception $e) {
            throw Unable_To_Check_File_Existence::for_location($path, $e);
        }
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    public function read(string $path): string
    {
        $body = $this->read_object($path);
        return $body->get_content_as_string();
    }
    public function read_stream(string $path)
    {
        $body = $this->read_object($path);
        return $body->get_content_as_resource();
    }
    public function delete(string $path): void
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        try {
            $this->client->delete_object($arguments);
        } catch (Throwable $exception) {
            throw Unable_To_Delete_File::at_location($path, '', $exception);
        }
    }
    public function delete_directory(string $path): void
    {
        $prefix = $this->prefixer->prefix_directory_path($path);
        $prefix = ltrim($prefix, '/');
        $objects = [];
        $params = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        try {
            $result = $this->client->list_objects_v2($params);
            /** @var AwsObject $item */
            foreach ($result->get_contents() as $item) {
                $key = $item->get_key();
                if (null !== $key) {
                    $objects[] = $this->create_object_identifier_for_xml_request($key);
                }
            }
            if (empty($objects)) {
                return;
            }
            foreach (array_chunk($objects, 1000) as $chunk) {
                $this->client->delete_objects(['Bucket' => $this->bucket, 'Delete' => ['Objects' => $chunk]]);
            }
        } catch (\Throwable $e) {
            throw Unable_To_Delete_Directory::at_location($path, $e->get_message(), $e);
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $default_visibility = $config->get(Config::OPTION_DIRECTORY_VISIBILITY, $this->visibility->default_for_directories());
        $config = $config->with_defaults([Config::OPTION_VISIBILITY => $default_visibility]);
        try {
            $this->upload(rtrim($path, '/') . '/', '', $config);
        } catch (Throwable $e) {
            throw Unable_To_Create_Directory::due_to_failure($path, $e);
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path), 'ACL' => $this->visibility->visibility_to_acl($visibility)];
        try {
            $this->client->put_object_acl($arguments);
        } catch (Throwable $exception) {
            throw Unable_To_Set_Visibility::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function visibility(string $path): File_Attributes
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        try {
            $result = $this->client->get_object_acl($arguments);
            $grants = $result->get_grants();
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::visibility($path, $exception->get_message(), $exception);
        }
        $visibility = $this->visibility->acl_to_visibility($grants);
        return new File_Attributes($path, null, $visibility);
    }
    public function mime_type(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_MIME_TYPE);
        if (null === $attributes->mime_type()) {
            throw Unable_To_Retrieve_Metadata::mime_type($path);
        }
        return $attributes;
    }
    public function last_modified(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_LAST_MODIFIED);
        if (null === $attributes->last_modified()) {
            throw Unable_To_Retrieve_Metadata::last_modified($path);
        }
        return $attributes;
    }
    public function file_size(string $path): File_Attributes
    {
        $attributes = $this->fetch_file_metadata($path, File_Attributes::ATTRIBUTE_FILE_SIZE);
        if (null === $attributes->file_size()) {
            throw Unable_To_Retrieve_Metadata::file_size($path);
        }
        return $attributes;
    }
    public function directory_exists(string $path): bool
    {
        try {
            $prefix = $this->prefixer->prefix_directory_path($path);
            $options = ['Bucket' => $this->bucket, 'Prefix' => $prefix, 'MaxKeys' => 1, 'Delimiter' => '/'];
            return $this->client->list_objects_v2($options)->get_key_count() > 0;
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $path = trim($path, '/');
        $prefix = trim($this->prefixer->prefix_path($path), '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';
        $options = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        if (false === $deep) {
            $options['Delimiter'] = '/';
        }
        try {
            $listing = $this->retrieve_paginated_listing($options);
            foreach ($listing as $item) {
                $item = $this->map_s3object_metadata($item);
                if ($item->path() === $path) {
                    continue;
                }
                yield $item;
            }
        } catch (\Throwable $e) {
            throw Unable_To_List_Contents::at_location($path, $deep, $e);
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
        } catch (Throwable $exception) {
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
        $arguments = ['ACL' => $this->visibility->visibility_to_acl($visibility ?: 'private'), 'Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($destination), 'CopySource' => rawurlencode($this->bucket . '/' . $this->prefixer->prefix_path($source))];
        try {
            $this->client->copy_object($arguments);
        } catch (Throwable $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    /**
     * @param string|resource $body
     */
    private function upload(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefix_path($path);
        $acl = $this->determine_acl($config);
        $options = $this->create_options_from_config($config);
        $should_determine_mimetype = '' !== $body && !\array_key_exists('ContentType', $options);
        if ($should_determine_mimetype && $mime_type = $this->mime_type_detector->detect_mime_type($key, $body)) {
            $options['ContentType'] = $mime_type;
        }
        try {
            if ($this->client instanceof Simple_S3client) {
                // Supports upload of files larger than 5GB
                $this->client->upload($this->bucket, $key, $body, array_merge($options, ['ACL' => $acl]));
            } else {
                $this->client->put_object(array_merge($options, ['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $body, 'ACL' => $acl]));
            }
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
        $options = [];
        foreach ($this->forwarded_options as $option) {
            $value = $config->get($option, '__NOT_SET__');
            if ('__NOT_SET__' !== $value) {
                $options[$option] = $value;
            }
        }
        return $options;
    }
    private function fetch_file_metadata(string $path, string $type): File_Attributes
    {
        $arguments = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        try {
            $result = $this->client->head_object($arguments);
            $result->resolve();
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, $exception->get_message(), $exception);
        }
        $attributes = $this->map_s3object_metadata($result, $path);
        if (!$attributes instanceof File_Attributes) {
            throw Unable_To_Retrieve_Metadata::create($path, $type, 'Unable to retrieve file attributes, directory attributes received.');
        }
        return $attributes;
    }
    /**
     * @param HeadObjectOutput|AwsObject|CommonPrefix $item
     */
    private function map_s3object_metadata($item, ?string $path = null): Storage_Attributes
    {
        if (null === $path) {
            if ($item instanceof Aws_Object) {
                $path = $this->prefixer->strip_prefix($item->get_key() ?? '');
            } elseif ($item instanceof Common_Prefix) {
                $path = $this->prefixer->strip_prefix($item->get_prefix() ?? '');
            } else {
                throw new \RuntimeException(sprintf('Argument 2 of "%s" cannot be null when $item is not instance of "%s" or %s', __METHOD__, Aws_Object::class, Common_Prefix::class));
            }
        }
        if (str_ends_with($path, '/')) {
            return new Directory_Attributes(rtrim($path, '/'));
        }
        $mime_type = null;
        $file_size = null;
        $last_modified = null;
        $date_time = null;
        $metadata = [];
        if ($item instanceof Aws_Object) {
            $date_time = $item->get_last_modified();
            $file_size = $item->get_size();
        } elseif ($item instanceof Common_Prefix) {
            // No data available
        } elseif ($item instanceof Head_Object_Output) {
            $mime_type = $item->get_content_type();
            $file_size = $item->get_content_length();
            $date_time = $item->get_last_modified();
            $metadata = $this->extract_extra_metadata($item);
        } else {
            throw new \RuntimeException(sprintf('Object of class "%s" is not supported in %s()', $item::class, __METHOD__));
        }
        if ($date_time instanceof \DateTimeInterface) {
            $last_modified = $date_time->get_timestamp();
        }
        return new File_Attributes($path, $file_size !== null ? (int) $file_size : null, null, $last_modified, $mime_type, $metadata);
    }
    /**
     * @param HeadObjectOutput $metadata
     */
    private function extract_extra_metadata($metadata): array
    {
        $extracted = [];
        foreach ($this->metadata_fields as $field) {
            $method = 'get' . $field;
            if (!method_exists($metadata, $method)) {
                continue;
            }
            $value = $metadata->{$method}();
            if (null !== $value) {
                $extracted[$field] = $value;
            }
        }
        return $extracted;
    }
    private function retrieve_paginated_listing(array $options): Generator
    {
        $result = $this->client->list_objects_v2($options);
        foreach ($result as $item) {
            yield $item;
        }
    }
    private function read_object(string $path): Result_Stream
    {
        $options = ['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)];
        try {
            return $this->client->get_object($options)->get_body();
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    private function create_object_identifier_for_xml_request(string $key): Object_Identifier
    {
        $escaped_key = htmlentities($key, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($escaped_key === '') {
            throw new \RuntimeException(sprintf('Cannot escape key "%s" for XML request, htmlentities() returned an empty string.', $key));
        }
        return new Object_Identifier(['Key' => $escaped_key]);
    }
    public function public_url(string $path, Config $config): string
    {
        if (!$this->client instanceof Simple_S3client) {
            throw Unable_To_Generate_Public_Url::no_generator_configured($path, 'Client needs to be instance of SimpleS3Client');
        }
        try {
            return $this->client->get_url($this->bucket, $this->prefixer->prefix_path($path));
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
            $request = new Get_Object_Request(['Bucket' => $this->bucket, 'Key' => $this->prefixer->prefix_path($path)] + $config->get('get_object_options', []));
            return $this->client->presign($request, DateTimeImmutable::create_from_interface($expires_at));
        } catch (Throwable $exception) {
            throw Unable_To_Generate_Temporary_Url::due_to_error($path, $exception);
        }
    }
}