<?php

declare (strict_types=1);
namespace League\Flysystem\Azure_Blob_Storage;

use function base64_decode;
use function bin2hex;
use DateTime;
use DateTimeInterface;
use League\Flysystem\Checksum_Algo_Is_Not_Supported;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
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
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use Microsoft_Azure\Storage\Blob\Blob_Rest_Proxy;
use Microsoft_Azure\Storage\Blob\Blob_Shared_Access_Signature_Helper;
use Microsoft_Azure\Storage\Blob\Models\Blob_Properties;
use Microsoft_Azure\Storage\Blob\Models\Create_Block_Blob_Options;
use Microsoft_Azure\Storage\Blob\Models\List_Blobs_Options;
use Microsoft_Azure\Storage\Common\Exceptions\Service_Exception;
use Microsoft_Azure\Storage\Common\Internal\Resources;
use Microsoft_Azure\Storage\Common\Internal\Storage_Service_Settings;
use Microsoft_Azure\Storage\Common\Models\Continuation_Token;
use function stream_get_contents;
use Throwable;
class Azure_Blob_Storage_Adapter implements Filesystem_Adapter, Public_Url_Generator, Checksum_Provider, Temporary_Url_Generator
{
    /** @var string[] */
    private const META_OPTIONS = ['CacheControl', 'ContentType', 'Metadata', 'ContentLanguage', 'ContentEncoding'];
    public const ON_VISIBILITY_THROW_ERROR = 'throw';
    public const ON_VISIBILITY_IGNORE = 'ignore';
    private Mime_Type_Detector $mime_type_detector;
    private Path_Prefixer $prefixer;
    public function __construct(private Blob_Rest_Proxy $client, private string $container, string $prefix = '', ?Mime_Type_Detector $mime_type_detector = null, private int $max_results_for_contents_listing = 5000, private string $visibility_handling = self::ON_VISIBILITY_THROW_ERROR, private ?Storage_Service_Settings $service_settings = null)
    {
        $this->prefixer = new Path_Prefixer($prefix);
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $resolved_destination = $this->prefixer->prefix_path($destination);
        $resolved_source = $this->prefixer->prefix_path($source);
        try {
            $this->client->copy_blob($this->container, $resolved_destination, $this->container, $resolved_source);
        } catch (Throwable $throwable) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $throwable);
        }
    }
    public function delete(string $path): void
    {
        $location = $this->prefixer->prefix_path($path);
        try {
            $this->client->delete_blob($this->container, $location);
        } catch (Throwable $exception) {
            if ($exception instanceof Service_Exception && $exception->get_code() === 404) {
                return;
            }
            throw Unable_To_Delete_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function read(string $path): string
    {
        $response = $this->read_stream($path);
        return stream_get_contents($response);
    }
    public function read_stream(string $path)
    {
        $location = $this->prefixer->prefix_path($path);
        try {
            $response = $this->client->get_blob($this->container, $location);
            return $response->get_content_stream();
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    public function list_contents(string $path, bool $deep = false): iterable
    {
        $resolved = $this->prefixer->prefix_directory_path($path);
        $options = new List_Blobs_Options();
        $options->set_prefix($resolved);
        $options->set_max_results($this->max_results_for_contents_listing);
        if ($deep === false) {
            $options->set_delimiter('/');
        }
        do {
            $response = $this->client->list_blobs($this->container, $options);
            foreach ($response->get_blob_prefixes() as $blob_prefix) {
                yield new Directory_Attributes($this->prefixer->strip_directory_prefix($blob_prefix->get_name()));
            }
            foreach ($response->get_blobs() as $blob) {
                yield $this->normalize_blob_properties($this->prefixer->strip_prefix($blob->get_name()), $blob->get_properties());
            }
            $continuation_token = $response->get_continuation_token();
            $options->set_continuation_token($continuation_token);
        } while ($continuation_token instanceof Continuation_Token);
    }
    public function file_exists(string $path): bool
    {
        $resolved = $this->prefixer->prefix_path($path);
        try {
            return $this->fetch_metadata($resolved) !== null;
        } catch (Throwable $exception) {
            if ($exception instanceof Service_Exception && $exception->get_code() === 404) {
                return false;
            }
            throw Unable_To_Check_File_Existence::for_location($path, $exception);
        }
    }
    public function directory_exists(string $path): bool
    {
        $resolved = $this->prefixer->prefix_directory_path($path);
        $options = new List_Blobs_Options();
        $options->set_prefix($resolved);
        $options->set_max_results(1);
        try {
            $list_results = $this->client->list_blobs($this->container, $options);
            return count($list_results->get_blobs()) > 0;
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
    }
    public function delete_directory(string $path): void
    {
        $resolved = $this->prefixer->prefix_directory_path($path);
        $options = new List_Blobs_Options();
        $options->set_prefix($resolved);
        try {
            start:
            $list_results = $this->client->list_blobs($this->container, $options);
            foreach ($list_results->get_blobs() as $blob) {
                $this->client->delete_blob($this->container, $blob->get_name());
            }
            $continuation_token = $list_results->get_continuation_token();
            if ($continuation_token instanceof Continuation_Token) {
                $options->set_continuation_token($continuation_token);
                goto start;
            }
        } catch (Throwable $exception) {
            throw Unable_To_Delete_Directory::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        // this is not supported by Azure
    }
    public function set_visibility(string $path, string $visibility): void
    {
        if ($this->visibility_handling === self::ON_VISIBILITY_THROW_ERROR) {
            throw Unable_To_Set_Visibility::at_location($path, 'Azure does not support this operation.');
        }
    }
    public function visibility(string $path): File_Attributes
    {
        throw Unable_To_Retrieve_Metadata::visibility($path, 'Azure does not support visibility');
    }
    public function mime_type(string $path): File_Attributes
    {
        try {
            return $this->fetch_metadata($this->prefixer->prefix_path($path));
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, $exception->get_message(), $exception);
        }
    }
    public function last_modified(string $path): File_Attributes
    {
        try {
            return $this->fetch_metadata($this->prefixer->prefix_path($path));
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, $exception->get_message(), $exception);
        }
    }
    public function file_size(string $path): File_Attributes
    {
        try {
            return $this->fetch_metadata($this->prefixer->prefix_path($path));
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::file_size($path, $exception->get_message(), $exception);
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
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }
    /**
     * @param string|resource $contents
     */
    private function upload(string $destination, $contents, Config $config): void
    {
        $resolved = $this->prefixer->prefix_path($destination);
        try {
            $options = $this->get_options_from_config($config);
            if (empty($options->get_content_type())) {
                $options->set_content_type($this->mime_type_detector->detect_mime_type($resolved, $contents));
            }
            $this->client->create_block_blob($this->container, $resolved, $contents, $options);
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($destination, $exception->get_message(), $exception);
        }
    }
    private function fetch_metadata(string $path): File_Attributes
    {
        return $this->normalize_blob_properties($path, $this->client->get_blob_properties($this->container, $path)->get_properties());
    }
    private function get_options_from_config(Config $config): Create_Block_Blob_Options
    {
        $options = new Create_Block_Blob_Options();
        foreach (self::META_OPTIONS as $option) {
            $setting = $config->get($option, '___NOT__SET___');
            if ($setting === '___NOT__SET___') {
                continue;
            }
            call_user_func([$options, "set{$option}"], $setting);
        }
        $mime_type = $config->get('mimetype');
        if ($mime_type !== null) {
            $options->set_content_type($mime_type);
        }
        return $options;
    }
    private function normalize_blob_properties(string $path, Blob_Properties $properties): File_Attributes
    {
        return new File_Attributes($path, $properties->get_content_length(), null, $properties->get_last_modified()->get_timestamp(), $properties->get_content_type(), ['md5_checksum' => $properties->get_content_md5()]);
    }
    public function public_url(string $path, Config $config): string
    {
        $location = $this->prefixer->prefix_path($path);
        return $this->client->get_blob_url($this->container, $location);
    }
    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'md5');
        if ($algo !== 'md5') {
            throw new Checksum_Algo_Is_Not_Supported();
        }
        try {
            $metadata = $this->fetch_metadata($this->prefixer->prefix_path($path));
            $checksum = $metadata->extra_metadata()['md5_checksum'] ?? '__not_specified';
        } catch (Throwable $exception) {
            throw new Unable_To_Provide_Checksum($exception->get_message(), $path, $exception);
        }
        if ($checksum === '__not_specified') {
            throw new Unable_To_Provide_Checksum('No checksum provided in metadata', $path);
        }
        return bin2hex(base64_decode($checksum));
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
    {
        if (!$this->service_settings instanceof Storage_Service_Settings) {
            throw Unable_To_Generate_Temporary_Url::no_generator_configured($path, 'The $serviceSettings constructor parameter must be set to generate temporary URLs.');
        }
        try {
            $sas = new Blob_Shared_Access_Signature_Helper($this->service_settings->get_name(), $this->service_settings->get_key());
            $base_url = $this->public_url($path, $config);
            $resource_name = $this->container . '/' . ltrim($this->prefixer->prefix_path($path), '/');
            $token = $sas->generate_blob_service_shared_access_signature_token(
                Resources::RESOURCE_TYPE_BLOB,
                $resource_name,
                'r',
                // read
                DateTime::create_from_interface($expires_at),
                $config->get('signed_start', ''),
                $config->get('signed_ip', ''),
                $config->get('signed_protocol', 'https'),
                $config->get('signed_identifier', ''),
                $config->get('cache_control', ''),
                $config->get('content_disposition', $config->get('content_deposition', '')),
                $config->get('content_encoding', ''),
                $config->get('content_language', ''),
                $config->get('content_type', '')
            );
            return "{$base_url}?{$token}";
        } catch (Throwable $exception) {
            throw Unable_To_Generate_Temporary_Url::due_to_error($path, $exception);
        }
    }
}