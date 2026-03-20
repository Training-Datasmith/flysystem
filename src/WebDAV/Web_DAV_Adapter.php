<?php

declare (strict_types=1);
namespace League\Flysystem\Web_Dav;

use function array_key_exists;
use function array_shift;
use function dirname;
use function explode;
use function fclose;
use function implode;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Unable_To_Check_Directory_Existence;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use function parse_url;
use function rawurldecode;
use RuntimeException;
use Sabre\DAV\Client;
use Sabre\DAV\Xml\Property\Resource_Type;
use Sabre\HTTP\Client_Http_Exception;
use Sabre\HTTP\Request;
use Throwable;
class Web_Dav_Adapter implements Filesystem_Adapter, Public_Url_Generator
{
    public const ON_VISIBILITY_THROW_ERROR = 'throw';
    public const ON_VISIBILITY_IGNORE = 'ignore';
    public const FIND_PROPERTIES = ['{DAV:}displayname', '{DAV:}getcontentlength', '{DAV:}getcontenttype', '{DAV:}getlastmodified', '{DAV:}iscollection', '{DAV:}resourcetype'];
    private Path_Prefixer $prefixer;
    public function __construct(private Client $client, string $prefix = '', private string $visibility_handling = self::ON_VISIBILITY_THROW_ERROR, private bool $manual_copy = false, private bool $manual_move = false)
    {
        $this->prefixer = new Path_Prefixer($prefix);
    }
    public function file_exists(string $path): bool
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $properties = $this->client->prop_find($location, ['{DAV:}resourcetype', '{DAV:}iscollection']);
            return !$this->props_is_directory($properties);
        } catch (Throwable $exception) {
            if ($exception instanceof Client_Http_Exception && $exception->get_http_status() === 404) {
                return false;
            }
            throw Unable_To_Check_File_Existence::for_location($path, $exception);
        }
    }
    protected function encode_path(string $path): string
    {
        $parts = explode('/', $path);
        foreach ($parts as $i => $part) {
            $parts[$i] = rawurlencode($part);
        }
        return implode('/', $parts);
    }
    public function directory_exists(string $path): bool
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $properties = $this->client->prop_find($location, ['{DAV:}resourcetype', '{DAV:}iscollection']);
            return $this->props_is_directory($properties);
        } catch (Throwable $exception) {
            if ($exception instanceof Client_Http_Exception && $exception->get_http_status() === 404) {
                return false;
            }
            throw Unable_To_Check_Directory_Existence::for_location($path, $exception);
        }
    }
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }
    /**
     * @param resource|string $contents
     */
    private function upload(string $path, mixed $contents): void
    {
        $this->create_parent_dir_for($path);
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $response = $this->client->request('PUT', $location, $contents);
            $status_code = $response['statusCode'];
            if ($status_code < 200 || $status_code >= 300) {
                throw new RuntimeException('Unexpected status code received: ' . $status_code);
            }
        } catch (Throwable $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function read(string $path): string
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $response = $this->client->request('GET', $location);
            if ($response['statusCode'] !== 200) {
                throw new RuntimeException('Unexpected response code for GET: ' . $response['statusCode']);
            }
            return $response['body'];
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    public function read_stream(string $path)
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $url = $this->client->get_absolute_url($location);
            $request = new Request('GET', $url);
            $response = $this->client->send($request);
            $status = $response->get_status();
            if ($status !== 200) {
                throw new RuntimeException('Unexpected response code for GET: ' . $status);
            }
            return $response->get_body_as_stream();
        } catch (Throwable $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    public function delete(string $path): void
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $response = $this->client->request('DELETE', $location);
            $status_code = $response['statusCode'];
            if ($status_code !== 404 && ($status_code < 200 || $status_code >= 300)) {
                throw new RuntimeException('Unexpected status code received while deleting file: ' . $status_code);
            }
        } catch (Throwable $exception) {
            if (!($exception instanceof Client_Http_Exception && $exception->get_code() === 404)) {
                throw Unable_To_Delete_File::at_location($path, $exception->get_message(), $exception);
            }
        }
    }
    public function delete_directory(string $path): void
    {
        $location = $this->encode_path($this->prefixer->prefix_directory_path($path));
        try {
            $status_code = $this->client->request('DELETE', $location)['statusCode'];
            if ($status_code !== 404 && ($status_code < 200 || $status_code >= 300)) {
                throw new RuntimeException('Unexpected status code received while deleting file: ' . $status_code);
            }
        } catch (Throwable $exception) {
            if (!($exception instanceof Client_Http_Exception && $exception->get_code() === 404)) {
                throw Unable_To_Delete_Directory::at_location($path, $exception->get_message(), $exception);
            }
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $parts = explode('/', $this->prefixer->prefix_directory_path($path));
        $directory_parts = [];
        foreach ($parts as $directory) {
            if ($directory === '.' || $directory === '') {
                return;
            }
            $directory_parts[] = $directory;
            $directory_path = implode('/', $directory_parts);
            $location = $this->encode_path($directory_path) . '/';
            if ($this->directory_exists($this->prefixer->strip_directory_prefix($directory_path))) {
                continue;
            }
            try {
                $response = $this->client->request('MKCOL', $location);
            } catch (Throwable $exception) {
                throw Unable_To_Create_Directory::due_to_failure($path, $exception);
            }
            if ($response['statusCode'] === 405) {
                continue;
            }
            if ($response['statusCode'] !== 201) {
                throw Unable_To_Create_Directory::at_location($path, 'Failed to create directory at: ' . $location);
            }
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        if ($this->visibility_handling === self::ON_VISIBILITY_THROW_ERROR) {
            throw Unable_To_Set_Visibility::at_location($path, 'WebDAV does not support this operation.');
        }
    }
    public function visibility(string $path): File_Attributes
    {
        throw Unable_To_Retrieve_Metadata::visibility($path, 'WebDAV does not support this operation.');
    }
    public function mime_type(string $path): File_Attributes
    {
        $mime_type = (string) $this->prop_find($path, 'mime_type', '{DAV:}getcontenttype');
        return new File_Attributes($path, mimeType: $mime_type);
    }
    public function last_modified(string $path): File_Attributes
    {
        $last_modified = $this->prop_find($path, 'last_modified', '{DAV:}getlastmodified');
        return new File_Attributes($path, lastModified: strtotime($last_modified));
    }
    public function file_size(string $path): File_Attributes
    {
        $file_size = (int) $this->prop_find($path, 'file_size', '{DAV:}getcontentlength');
        return new File_Attributes($path, fileSize: $file_size);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $location = $this->encode_path($this->prefixer->prefix_directory_path($path));
        $response = $this->client->prop_find($location, self::FIND_PROPERTIES, 1);
        // This is the directory itself, the files are subsequent entries.
        array_shift($response);
        foreach ($response as $path => $object) {
            $path = (string) parse_url(rawurldecode($path), PHP_URL_PATH);
            $path = $this->prefixer->strip_prefix($path);
            $object = $this->normalize_object($object);
            if ($this->props_is_directory($object)) {
                yield new Directory_Attributes($path, lastModified: $object['last_modified'] ?? null);
                if (!$deep) {
                    continue;
                }
                foreach ($this->list_contents($path, true) as $child) {
                    yield $child;
                }
            } else {
                yield new File_Attributes($path, fileSize: $object['file_size'] ?? null, lastModified: $object['last_modified'] ?? null, mimeType: $object['mime_type'] ?? null);
            }
        }
    }
    private function normalize_object(array $object): array
    {
        $mapping = ['{DAV:}getcontentlength' => 'file_size', '{DAV:}getcontenttype' => 'mime_type', 'content-length' => 'file_size', 'content-type' => 'mime_type'];
        foreach ($mapping as $from => $to) {
            if (array_key_exists($from, $object)) {
                $object[$to] = $object[$from];
            }
        }
        array_key_exists('file_size', $object) && $object['file_size'] = (int) $object['file_size'];
        if (array_key_exists('{DAV:}getlastmodified', $object)) {
            $object['last_modified'] = strtotime($object['{DAV:}getlastmodified']);
        }
        return $object;
    }
    public function move(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }
        if ($this->manual_move) {
            $this->manual_move($source, $destination);
            return;
        }
        $this->create_parent_dir_for($destination);
        $location = $this->encode_path($this->prefixer->prefix_path($source));
        $new_location = $this->encode_path($this->prefixer->prefix_path($destination));
        try {
            $response = $this->client->request('MOVE', $location, null, ['Destination' => $this->client->get_absolute_url($new_location)]);
            if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
                throw new RuntimeException('MOVE command returned unexpected status code: ' . $response['statusCode'] . "\n{$response['body']}");
            }
        } catch (Throwable $e) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $e);
        }
    }
    private function manual_move(string $source, string $destination): void
    {
        try {
            $handle = $this->read_stream($source);
            $this->write_stream($destination, $handle, new Config());
            @fclose($handle);
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
        if ($this->manual_copy) {
            $this->manual_copy($source, $destination);
            return;
        }
        $this->create_parent_dir_for($destination);
        $location = $this->encode_path($this->prefixer->prefix_path($source));
        $new_location = $this->encode_path($this->prefixer->prefix_path($destination));
        try {
            $response = $this->client->request('COPY', $location, null, ['Destination' => $this->client->get_absolute_url($new_location)]);
            if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
                throw new RuntimeException('COPY command returned unexpected status code: ' . $response['statusCode']);
            }
        } catch (Throwable $e) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $e);
        }
    }
    private function manual_copy(string $source, string $destination): void
    {
        try {
            $handle = $this->read_stream($source);
            $this->write_stream($destination, $handle, new Config());
            @fclose($handle);
        } catch (Throwable $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    private function props_is_directory(array $properties): bool
    {
        if (isset($properties['{DAV:}resourcetype'])) {
            /** @var ResourceType $resourceType */
            $resource_type = $properties['{DAV:}resourcetype'];
            return $resource_type->is('{DAV:}collection');
        }
        return isset($properties['{DAV:}iscollection']) && $properties['{DAV:}iscollection'] === '1';
    }
    private function create_parent_dir_for(string $path): void
    {
        $dirname = dirname($path);
        if ($this->directory_exists($dirname)) {
            return;
        }
        $this->create_directory($dirname, new Config());
    }
    private function prop_find(string $path, string $section, string $property): mixed
    {
        $location = $this->encode_path($this->prefixer->prefix_path($path));
        try {
            $result = $this->client->prop_find($location, [$property]);
            if (!array_key_exists($property, $result)) {
                throw new RuntimeException('Invalid response, missing key: ' . $property);
            }
            return $result[$property];
        } catch (Throwable $exception) {
            throw Unable_To_Retrieve_Metadata::create($path, $section, $exception->get_message(), $exception);
        }
    }
    public function public_url(string $path, Config $config): string
    {
        return $this->client->get_absolute_url($this->encode_path($this->prefixer->prefix_path($path)));
    }
}