<?php

declare (strict_types=1);
namespace League\Flysystem\Grid_Fs;

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
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use League\Mime_Type_Detection\Mime_Type_Detector;
use Mongo_Db\BSON\Object_Id;
use Mongo_Db\BSON\Regex;
use Mongo_Db\BSON\Utc_Date_Time;
use Mongo_Db\Driver\Exception\Exception;
use Mongo_Db\Grid_Fs\Bucket;
use Mongo_Db\Grid_Fs\Exception\File_Not_Found_Exception;
/**
 * @phpstan-type GridFile array{_id:ObjectId, length:int, chunkSize:int, uploadDate:UTCDateTime, filename:string, metadata?:array{contentType?:string, flysystem_visibility?:string}}
 */
class Grid_Fs_Adapter implements Filesystem_Adapter
{
    private const METADATA_DIRECTORY = 'flysystem_directory';
    private const METADATA_VISIBILITY = 'flysystem_visibility';
    private const METADATA_MIMETYPE = 'contentType';
    private const TYPEMAP_ARRAY = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'], 'codec' => null];
    private Bucket $bucket;
    private Path_Prefixer $prefixer;
    private Mime_Type_Detector $mime_type_detector;
    public function __construct(Bucket $bucket, string $prefix = '', ?Mime_Type_Detector $mime_type_detector = null)
    {
        $this->bucket = $bucket;
        $this->prefixer = new Path_Prefixer($prefix);
        $this->mime_type_detector = $mime_type_detector ?? new Finfo_Mime_Type_Detector();
    }
    public function file_exists(string $path): bool
    {
        $file = $this->find_file($path);
        return $file !== null;
    }
    public function directory_exists(string $path): bool
    {
        // A directory exists if at least one file exists with a path starting with the directory name
        $files = $this->list_contents($path, true);
        foreach ($files as $file) {
            return true;
        }
        return false;
    }
    public function write(string $path, string $contents, Config $config): void
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Write_File::at_location($path, 'file path cannot end with a slash');
        }
        $filename = $this->prefixer->prefix_path($path);
        $options = ['metadata' => $config->get('metadata', [])];
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $options['metadata'][self::METADATA_VISIBILITY] = $visibility;
        }
        if (($mime_type = $config->get('mimetype')) || $mime_type = $this->mime_type_detector->detect_mime_type($path, $contents)) {
            $options['metadata'][self::METADATA_MIMETYPE] = $mime_type;
        }
        try {
            $stream = $this->bucket->open_upload_stream($filename, $options);
            fwrite($stream, $contents);
            fclose($stream);
        } catch (Exception $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function write_stream(string $path, $contents, Config $config): void
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Write_File::at_location($path, 'file path cannot end with a slash');
        }
        $filename = $this->prefixer->prefix_path($path);
        $options = [];
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $options['metadata'][self::METADATA_VISIBILITY] = $visibility;
        }
        if (($mimetype = $config->get('mimetype')) || $mimetype = $this->mime_type_detector->detect_mime_type_from_path($path)) {
            $options['metadata'][self::METADATA_MIMETYPE] = $mimetype;
        }
        try {
            $this->bucket->upload_from_stream($filename, $contents, $options);
        } catch (Exception $exception) {
            throw Unable_To_Write_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function read(string $path): string
    {
        $stream = $this->read_stream($path);
        try {
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }
    public function read_stream(string $path)
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Read_File::from_location($path, 'file path cannot end with a slash');
        }
        try {
            $filename = $this->prefixer->prefix_path($path);
            return $this->bucket->open_download_stream_by_name($filename);
        } catch (File_Not_Found_Exception $exception) {
            throw Unable_To_Read_File::from_location($path, 'file does not exist', $exception);
        } catch (Exception $exception) {
            throw Unable_To_Read_File::from_location($path, $exception->get_message(), $exception);
        }
    }
    /**
     * Delete all revisions of the file name, starting with the oldest,
     * no-op if the file does not exist.
     *
     * @throws UnableToDeleteFile
     */
    public function delete(string $path): void
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Delete_File::at_location($path, 'file path cannot end with a slash');
        }
        $filename = $this->prefixer->prefix_path($path);
        try {
            $this->find_and_delete(['filename' => $filename]);
        } catch (Exception $exception) {
            throw Unable_To_Delete_File::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function delete_directory(string $path): void
    {
        $prefixed_path = $this->prefixer->prefix_directory_path($path);
        try {
            $this->find_and_delete(['filename' => new Regex('^' . preg_quote($prefixed_path))]);
        } catch (Exception $exception) {
            throw Unable_To_Delete_Directory::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function create_directory(string $path, Config $config): void
    {
        $dirname = $this->prefixer->prefix_directory_path($path);
        $options = ['metadata' => $config->get('metadata', []) + [self::METADATA_DIRECTORY => true]];
        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $options['metadata'][self::METADATA_VISIBILITY] = $visibility;
        }
        try {
            $stream = $this->bucket->open_upload_stream($dirname, $options);
            fwrite($stream, '');
            fclose($stream);
        } catch (Exception $exception) {
            throw Unable_To_Create_Directory::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $file = $this->find_file($path);
        if ($file === null) {
            throw Unable_To_Set_Visibility::at_location($path, 'file does not exist');
        }
        try {
            $this->bucket->get_files_collection()->update_one(['_id' => $file['_id']], ['$set' => ['metadata.' . self::METADATA_VISIBILITY => $visibility]]);
        } catch (Exception $exception) {
            throw Unable_To_Set_Visibility::at_location($path, $exception->get_message(), $exception);
        }
    }
    public function visibility(string $path): File_Attributes
    {
        $file = $this->find_file($path);
        if ($file === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'file does not exist');
        }
        return $this->map_file_attributes($file);
    }
    public function file_size(string $path): File_Attributes
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Retrieve_Metadata::file_size($path, 'file path cannot end with a slash');
        }
        $file = $this->find_file($path);
        if ($file === null) {
            throw Unable_To_Retrieve_Metadata::file_size($path, 'file does not exist');
        }
        return $this->map_file_attributes($file);
    }
    public function mime_type(string $path): File_Attributes
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'file path cannot end with a slash');
        }
        $file = $this->find_file($path);
        if ($file === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'file does not exist');
        }
        $attributes = $this->map_file_attributes($file);
        if ($attributes->mime_type() === null) {
            throw Unable_To_Retrieve_Metadata::mime_type($path, 'unknown');
        }
        return $attributes;
    }
    public function last_modified(string $path): File_Attributes
    {
        if (str_ends_with($path, '/')) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, 'file path cannot end with a slash');
        }
        $file = $this->find_file($path);
        if ($file === null) {
            throw Unable_To_Retrieve_Metadata::last_modified($path, 'file does not exist');
        }
        return $this->map_file_attributes($file);
    }
    public function list_contents(string $path, bool $deep): iterable
    {
        $path = $this->prefixer->prefix_directory_path($path);
        $pathdeep = 0;
        // Get the last revision of each file, using the index on the files collection
        $pipeline = [['$sort' => ['filename' => 1, 'uploadDate' => 1]]];
        if ($path !== '') {
            $pathdeep = substr_count($path, '/');
            // Exclude files that do not start with the expected path
            $pipeline[] = ['$match' => ['filename' => new Regex('^' . preg_quote($path))]];
        }
        if ($deep === false) {
            $pipeline[] = ['$addFields' => ['splitpath' => ['$split' => ['$filename', '/']]]];
            $pipeline[] = ['$group' => [
                // The same name could be used as a filename and as part of the path of other files
                '_id' => ['basename' => ['$arrayElemAt' => ['$splitpath', $pathdeep]], 'isDir' => ['$ne' => [['$size' => '$splitpath'], $pathdeep + 1]]],
                // Get the metadata of the last revision of each file
                'file' => ['$last' => '$$ROOT'],
                // The "lastModified" date is the date of the last uploaded file in the directory
                'uploadDate' => ['$max' => '$uploadDate'],
            ]];
            $files = $this->bucket->get_files_collection()->aggregate($pipeline, self::TYPEMAP_ARRAY);
            foreach ($files as $file) {
                if ($file['_id']['isDir']) {
                    yield new Directory_Attributes($this->prefixer->strip_directory_prefix($path . $file['_id']['basename']), null, $file['uploadDate']->to_date_time()->get_timestamp());
                } else {
                    yield $this->map_file_attributes($file['file']);
                }
            }
        } else {
            // Get the metadata of the last revision of each file
            $pipeline[] = ['$group' => ['_id' => '$filename', 'file' => ['$first' => '$$ROOT']]];
            $files = $this->bucket->get_files_collection()->aggregate($pipeline, self::TYPEMAP_ARRAY);
            foreach ($files as $file) {
                $file = $file['file'];
                if (str_ends_with($file['filename'], '/')) {
                    // Empty files with a trailing slash are markers for directories, only for Flysystem
                    yield new Directory_Attributes($this->prefixer->strip_directory_prefix($file['filename']), $file['metadata'][self::METADATA_VISIBILITY] ?? null, $file['uploadDate']->to_date_time()->get_timestamp(), $file);
                } else {
                    yield $this->map_file_attributes($file);
                }
            }
        }
    }
    public function move(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }
        if ($this->file_exists($destination)) {
            $this->delete($destination);
        }
        try {
            $result = $this->bucket->get_files_collection()->update_many(['filename' => $this->prefixer->prefix_path($source)], ['$set' => ['filename' => $this->prefixer->prefix_path($destination)]]);
            if ($result->get_modified_count() === 0) {
                throw Unable_To_Move_File::because('file does not exist', $source, $destination);
            }
        } catch (Exception $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
    }
    public function copy(string $source, string $destination, Config $config): void
    {
        $file = $this->find_file($source);
        if ($file === null) {
            throw Unable_To_Copy_File::from_location_to($source, $destination);
        }
        $options = [];
        if (($visibility = $config->get(Config::OPTION_VISIBILITY)) || $visibility = $file['metadata'][self::METADATA_VISIBILITY] ?? null) {
            $options['metadata'][self::METADATA_VISIBILITY] = $visibility;
        }
        if (($mimetype = $config->get('mimetype')) || $mimetype = $file['metadata'][self::METADATA_MIMETYPE] ?? null) {
            $options['metadata'][self::METADATA_MIMETYPE] = $mimetype;
        }
        try {
            $stream = $this->bucket->open_download_stream($file['_id']);
            $this->bucket->upload_from_stream($this->prefixer->prefix_path($destination), $stream, $options);
        } catch (Exception $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    /**
     * Get the last revision of the file name.
     *
     * @return GridFile|null
     */
    private function find_file(string $path): ?array
    {
        $filename = $this->prefixer->prefix_path($path);
        $files = $this->bucket->find(['filename' => $filename], ['sort' => ['uploadDate' => -1], 'limit' => 1] + self::TYPEMAP_ARRAY);
        return $files->to_array()[0] ?? null;
    }
    /**
     * @param GridFile $file
     */
    private function map_file_attributes(array $file): File_Attributes
    {
        return new File_Attributes($this->prefixer->strip_prefix($file['filename']), $file['length'], $file['metadata'][self::METADATA_VISIBILITY] ?? null, $file['uploadDate']->to_date_time()->get_timestamp(), $file['metadata'][self::METADATA_MIMETYPE] ?? null, $file);
    }
    /**
     * @throws Exception
     */
    private function find_and_delete(array $filter): void
    {
        $files = $this->bucket->find($filter, ['sort' => ['uploadDate' => 1], 'projection' => ['_id' => 1]] + self::TYPEMAP_ARRAY);
        foreach ($files as $file) {
            try {
                $this->bucket->delete($file['_id']);
            } catch (File_Not_Found_Exception) {
                // Ignore error due to race condition
            }
        }
    }
}