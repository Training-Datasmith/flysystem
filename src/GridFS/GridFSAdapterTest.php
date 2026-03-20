<?php

declare (strict_types=1);
namespace League\Flysystem\Grid_Fs;

use function getenv;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case as TestCase;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Write_File;
use Mongo_Db\Client;
use Mongo_Db\Database;
/**
 * @group gridfs
 *
 * @method GridFSAdapter adapter()
 */
class Grid_Fs_Adapter_Test extends Test_Case
{
    private static string $adapter_prefix = 'test-prefix';
    public static function tear_down_after_class(): void
    {
        self::get_database()->drop();
        parent::tear_down_after_class();
    }
    /**
     * @test
     */
    public function fetching_contains_extra_metadata(): void
    {
        $adapter = $this->adapter();
        $this->run_scenario(function () use ($adapter): void {
            $this->given_we_have_an_existing_file('file.txt');
            $file_attributes = $adapter->last_modified('file.txt');
            $extra = $file_attributes->extra_metadata();
            $this->assert_array_has_key('_id', $extra);
            $this->assert_array_has_key('filename', $extra);
        });
    }
    /**
     * @test
     */
    public function fetching_last_modified_of_a_directory(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = $this->adapter();
        $this->run_scenario(function () use ($adapter): void {
            $adapter->create_directory('path', new Config());
            $adapter->last_modified('path/');
        });
    }
    /**
     * @test
     */
    public function fetching_mime_type_of_a_directory(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = $this->adapter();
        $this->run_scenario(function () use ($adapter): void {
            $adapter->create_directory('path', new Config());
            $adapter->mime_type('path/');
        });
    }
    /**
     * @test
     */
    public function reading_a_file_with_trailing_slash(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read('foo/');
    }
    /**
     * @test
     */
    public function reading_a_file_stream_with_trailing_slash(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read_stream('foo/');
    }
    /**
     * @test
     */
    public function writing_a_file_with_trailing_slash(): void
    {
        $this->expect_exception(Unable_To_Write_File::class);
        $this->adapter()->write('foo/', 'contents', new Config());
    }
    /**
     * @test
     */
    public function writing_a_file_stream_with_trailing_slash(): void
    {
        $this->expect_exception(Unable_To_Write_File::class);
        $write_stream = stream_with_contents('contents');
        $this->adapter()->write_stream('foo/', $write_stream, new Config());
    }
    /**
     * @test
     */
    public function writing_a_file_with_a_invalid_stream(): void
    {
        $this->expect_exception(Unable_To_Write_File::class);
        // @phpstan-ignore argument.type
        $this->adapter()->write_stream('file.txt', 'foo', new Config());
    }
    /**
     * @test
     */
    public function delete_a_file_with_trailing_slash(): void
    {
        $this->expect_exception(Unable_To_Delete_File::class);
        $this->adapter()->delete('foo/');
    }
    /**
     * @test
     */
    public function reading_last_revision(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('file.txt', 'version 1');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 2');
            $this->assert_same('version 2', $this->adapter()->read('file.txt'));
        });
    }
    /**
     * @testWith [false]
     *           [true]
     *
     * @test
     */
    public function listing_contents_last_revision(bool $deep): void
    {
        $this->run_scenario(function () use ($deep): void {
            $this->given_we_have_an_existing_file('file.txt', 'version 1');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 2');
            $files = $this->adapter()->list_contents('', $deep);
            $files = iterator_to_array($files);
            $this->assert_count(1, $files);
            $file = $files[0];
            $this->assert_instance_of(File_Attributes::class, $file);
            $this->assert_same('file.txt', $file->path());
        });
    }
    /**
     * @test
     */
    public function listing_contents_directory_with_multiple_files(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('some/file-1.txt');
            $this->given_we_have_an_existing_file('some/file-2.txt');
            $this->given_we_have_an_existing_file('some/other/file-1.txt');
            $files = $this->adapter()->list_contents('', false);
            $files = iterator_to_array($files);
            $this->assert_count(1, $files);
            $file = $files[0];
            $this->assert_instance_of(Directory_Attributes::class, $file);
            $this->assert_same('some', $file->path());
        });
    }
    /**
     * @test
     */
    public function delete_all_revisions(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('file.txt', 'version 1');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 2');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 3');
            $this->adapter()->delete('file.txt');
            $this->assert_false($this->adapter()->file_exists('file.txt'), 'File does not exist');
        });
    }
    /**
     * @test
     */
    public function move_all_revisions(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('file.txt', 'version 1');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 2');
            usleep(1000);
            $this->given_we_have_an_existing_file('file.txt', 'version 3');
            $this->adapter()->move('file.txt', 'destination.txt', new Config());
            $this->assert_false($this->adapter()->file_exists('file.txt'));
            $this->assert_same($this->adapter()->read('destination.txt'), 'version 3');
        });
    }
    protected function tear_down(): void
    {
        self::get_database()->select_grid_fs_bucket()->drop();
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        $bucket = self::get_database()->select_grid_fs_bucket();
        $prefix = getenv('FLYSYSTEM_MONGODB_PREFIX') ?: self::$adapter_prefix;
        return new Grid_Fs_Adapter($bucket, $prefix);
    }
    private static function get_database(): Database
    {
        $uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017/';
        $client = new Client($uri);
        return $client->select_database(getenv('MONGODB_DATABASE') ?: 'flysystem_tests');
    }
}