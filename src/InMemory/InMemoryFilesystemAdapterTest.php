<?php

declare (strict_types=1);
namespace League\Flysystem\In_Memory;

use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Visibility;
use League\Mime_Type_Detection\Empty_Extension_To_Mime_Type_Map;
use League\Mime_Type_Detection\Extension_Mime_Type_Detector;
/**
 * @group in-memory
 */
class In_Memory_Filesystem_Adapter_Test extends Filesystem_Adapter_Test_Case
{
    public const PATH = 'path.txt';
    /**
     * @before
     */
    public function reset_function_mocks(): void
    {
        reset_function_mocks();
        /** @var InMemoryFilesystemAdapter $filesystemAdapter */
        $filesystem_adapter = $this->adapter();
        $filesystem_adapter->delete_everything();
    }
    /**
     * @test
     */
    public function getting_mimetype_on_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->adapter()->mime_type('path.txt');
    }
    /**
     * @test
     */
    public function getting_last_modified_on_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->adapter()->last_modified('path.txt');
    }
    /**
     * @test
     */
    public function getting_file_size_on_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->adapter()->file_size('path.txt');
    }
    /**
     * @test
     */
    public function deleting_a_file(): void
    {
        $this->adapter()->write('path.txt', 'contents', new Config());
        $this->assert_true($this->adapter()->file_exists('path.txt'));
        $this->adapter()->delete('path.txt');
        $this->assert_false($this->adapter()->file_exists('path.txt'));
    }
    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $adapter = $this->adapter();
        $adapter->write('a/path.txt', 'contents', new Config());
        $adapter->write('a/b/path.txt', 'contents', new Config());
        $adapter->write('a/b/c/path.txt', 'contents', new Config());
        $this->assert_true($adapter->file_exists('a/b/path.txt'));
        $this->assert_true($adapter->file_exists('a/b/c/path.txt'));
        $adapter->delete_directory('a/b');
        $this->assert_true($adapter->file_exists('a/path.txt'));
        $this->assert_false($adapter->file_exists('a/b/path.txt'));
        $this->assert_false($adapter->file_exists('a/b/c/path.txt'));
    }
    /**
     * @test
     */
    public function creating_a_directory_does_nothing(): void
    {
        $this->adapter()->create_directory('something', new Config());
        $this->assert_true(true);
    }
    /**
     * @test
     */
    public function writing_with_a_stream_and_reading_a_file(): void
    {
        $handle = stream_with_contents('contents');
        $this->adapter()->write_stream(self::PATH, $handle, new Config());
        $contents = $this->adapter()->read(self::PATH);
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function reading_a_stream(): void
    {
        $this->adapter()->write(self::PATH, 'contents', new Config());
        $contents = $this->adapter()->read_stream(self::PATH);
        $this->assert_equals('contents', stream_get_contents($contents));
        fclose($contents);
    }
    /**
     * @test
     */
    public function reading_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read('path.txt');
    }
    /**
     * @test
     */
    public function stream_reading_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read_stream('path.txt');
    }
    /**
     * @test
     */
    public function listing_all_files(): void
    {
        $adapter = $this->adapter();
        $adapter->write('path.txt', 'contents', new Config());
        $adapter->write('a/path.txt', 'contents', new Config());
        $adapter->write('a/b/path.txt', 'contents', new Config());
        /** @var StorageAttributes[] $listing */
        $listing = iterator_to_array($adapter->list_contents('/', true));
        $this->assert_count(5, $listing);
        $expected = ['path.txt' => Storage_Attributes::TYPE_FILE, 'a/path.txt' => Storage_Attributes::TYPE_FILE, 'a/b/path.txt' => Storage_Attributes::TYPE_FILE, 'a' => Storage_Attributes::TYPE_DIRECTORY, 'a/b' => Storage_Attributes::TYPE_DIRECTORY];
        foreach ($listing as $item) {
            $this->assert_array_has_key($item->path(), $expected);
            $this->assert_equals($item->type(), $expected[$item->path()]);
        }
    }
    /**
     * @test
     */
    public function listing_non_recursive(): void
    {
        $adapter = $this->adapter();
        $adapter->write('path.txt', 'contents', new Config());
        $adapter->write('a/path.txt', 'contents', new Config());
        $adapter->write('a/b/path.txt', 'contents', new Config());
        $listing = iterator_to_array($adapter->list_contents('/', false));
        $this->assert_count(2, $listing);
    }
    /**
     * @test
     */
    public function moving_a_file_successfully(): void
    {
        $adapter = $this->adapter();
        $adapter->write('path.txt', 'contents', new Config());
        $adapter->move('path.txt', 'new-path.txt', new Config());
        $this->assert_false($adapter->file_exists('path.txt'));
        $this->assert_true($adapter->file_exists('new-path.txt'));
    }
    /**
     * @test
     */
    public function trying_to_move_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Move_File::class);
        $this->adapter()->move('path.txt', 'new-path.txt', new Config());
    }
    /**
     * @test
     */
    public function copying_a_file_successfully(): void
    {
        $adapter = $this->adapter();
        $adapter->write('path.txt', 'contents', new Config());
        $adapter->copy('path.txt', 'new-path.txt', new Config());
        $this->assert_true($adapter->file_exists('path.txt'));
        $this->assert_true($adapter->file_exists('new-path.txt'));
    }
    /**
     * @test
     */
    public function trying_to_copy_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->adapter()->copy('path.txt', 'new-path.txt', new Config());
    }
    /**
     * @test
     */
    public function not_listing_directory_placeholders(): void
    {
        $adapter = $this->adapter();
        $adapter->create_directory('directory', new Config());
        $contents = iterator_to_array($adapter->list_contents('', true));
        $this->assert_count(1, $contents);
    }
    /**
     * @test
     */
    public function checking_for_metadata(): void
    {
        mock_function('time', 1234);
        $adapter = $this->adapter();
        $adapter->write(self::PATH, (string) file_get_contents(__DIR__ . '/../AdapterTestUtilities/test_files/flysystem.svg'), new Config());
        $this->assert_true($adapter->file_exists(self::PATH));
        $this->assert_equals(754, $adapter->file_size(self::PATH)->file_size());
        $this->assert_equals(1234, $adapter->last_modified(self::PATH)->last_modified());
        $this->assert_string_starts_with('image/svg+xml', $adapter->mime_type(self::PATH)->mime_type());
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->use_adapter(new In_Memory_Filesystem_Adapter(Visibility::PUBLIC, new Extension_Mime_Type_Detector(new Empty_Extension_To_Mime_Type_Map())));
        parent::fetching_unknown_mime_type_of_a_file();
    }
    /**
     * @test
     */
    public function using_custom_timestamp(): void
    {
        $adapter = $this->adapter();
        $now = 100;
        $adapter->write('file.txt', 'contents', new Config(['timestamp' => $now]));
        $this->assert_equals($now, $adapter->last_modified('file.txt')->last_modified());
        $earlier = 50;
        $adapter->copy('file.txt', 'new_file.txt', new Config(['timestamp' => $earlier]));
        $this->assert_equals($earlier, $adapter->last_modified('new_file.txt')->last_modified());
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        return new In_Memory_Filesystem_Adapter();
    }
}