<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use Generator;
use function iterator_to_array;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
/**
 * @group zip
 */
abstract class Zip_Archive_Adapter_Test_Case extends Filesystem_Adapter_Test_Case
{
    private const ARCHIVE = __DIR__ . '/test.zip';
    private static ?\League\Flysystem\Zip_Archive\Stub_Zip_Archive_Provider $archive_provider = null;
    protected function set_up(): void
    {
        static::$adapter = static::create_filesystem_adapter();
        static::remove_zip_archive();
        parent::set_up();
    }
    public static function tear_down_after_class(): void
    {
        static::remove_zip_archive();
    }
    protected function tear_down(): void
    {
        static::remove_zip_archive();
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        static::$archive_provider = new Stub_Zip_Archive_Provider(self::ARCHIVE);
        return new Zip_Archive_Adapter(self::$archive_provider, static::get_root());
    }
    abstract protected static function get_root(): string;
    /**
     * @test
     */
    public function not_being_able_to_create_the_parent_directory(): void
    {
        $this->expect_exception(Unable_To_Create_Parent_Directory::class);
        (new Zip_Archive_Adapter(new Stub_Zip_Archive_Provider('/no-way/this/will/work')))->write('haha', 'lol', new Config());
    }
    /**
     * @test
     */
    public function not_being_able_to_write_a_file_because_the_parent_directory_could_not_be_created(): void
    {
        self::$archive_provider->stubbed_zip_archive()->fail_next_directory_creation();
        $this->expect_exception(Unable_To_Write_File::class);
        $this->adapter()->write('directoryName/is-here/filename.txt', 'contents', new Config());
    }
    /**
     * @test
     *
     * @dataProvider scenariosThatCauseWritesToFail
     */
    public function scenarios_that_cause_writing_a_file_to_fail(callable $scenario): void
    {
        $this->run_scenario($scenario);
        $this->expect_exception(Unable_To_Write_File::class);
        $this->run_scenario(function (): void {
            $handle = stream_with_contents('contents');
            $this->adapter()->write_stream('some/path.txt', $handle, new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            is_resource($handle) && @fclose($handle);
        });
    }
    public static function scenarios_that_cause_writes_to_fail(): Generator
    {
        yield 'writing a file fails when writing' => [function (): void {
            static::$archive_provider->stubbed_zip_archive()->fail_next_write();
        }];
        yield 'writing a file fails when setting visibility' => [function (): void {
            static::$archive_provider->stubbed_zip_archive()->fail_when_setting_visibility();
        }];
        yield 'writing a file fails to get the stream contents' => [function (): void {
            mock_function('stream_get_contents', false);
        }];
    }
    /**
     * @test
     */
    public function failing_to_delete_a_file(): void
    {
        $this->given_we_have_an_existing_file('path.txt');
        static::$archive_provider->stubbed_zip_archive()->fail_next_delete_name();
        $this->expect_exception(Unable_To_Delete_File::class);
        $this->adapter()->delete('path.txt');
    }
    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $this->given_we_have_an_existing_file('a.txt');
        $this->given_we_have_an_existing_file('one/a.txt');
        $this->given_we_have_an_existing_file('one/b.txt');
        $this->given_we_have_an_existing_file('two/a.txt');
        $items = iterator_to_array($this->adapter()->list_contents('', true));
        $this->assert_count(6, $items);
        $this->adapter()->delete_directory('one');
        $items = iterator_to_array($this->adapter()->list_contents('', true));
        $this->assert_count(3, $items);
    }
    /**
     * @test
     */
    public function deleting_a_prefixed_directory(): void
    {
        $this->given_we_have_an_existing_file('a.txt');
        $this->given_we_have_an_existing_file('/one/a.txt');
        $this->given_we_have_an_existing_file('one/b.txt');
        $this->given_we_have_an_existing_file('two/a.txt');
        $items = iterator_to_array($this->adapter()->list_contents('', true));
        $this->assert_count(6, $items);
        $this->adapter()->delete_directory('one');
        $items = iterator_to_array($this->adapter()->list_contents('', true));
        $this->assert_count(3, $items);
    }
    /**
     * @test
     */
    public function list_root_directory(): void
    {
        $this->given_we_have_an_existing_file('a.txt');
        $this->given_we_have_an_existing_file('one/a.txt');
        $this->given_we_have_an_existing_file('one/b.txt');
        $this->given_we_have_an_existing_file('two/a.txt');
        $this->assert_count(6, iterator_to_array($this->adapter()->list_contents('', true)));
        $this->assert_count(3, iterator_to_array($this->adapter()->list_contents('', false)));
    }
    /**
     * @test
     */
    public function failing_to_create_a_directory(): void
    {
        static::$archive_provider->stubbed_zip_archive()->fail_next_directory_creation();
        $this->expect_exception(Unable_To_Create_Directory::class);
        $this->adapter()->create_directory('somewhere', new Config());
    }
    /**
     * @test
     */
    public function failing_to_create_a_directory_because_setting_visibility_fails(): void
    {
        static::$archive_provider->stubbed_zip_archive()->fail_when_setting_visibility();
        $this->expect_exception(Unable_To_Create_Directory::class);
        $this->adapter()->create_directory('somewhere', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PRIVATE]));
    }
    /**
     * @test
     */
    public function failing_to_delete_a_directory(): void
    {
        static::$archive_provider->stubbed_zip_archive()->fail_when_deleting_an_index();
        $this->given_we_have_an_existing_file('here/path.txt');
        $this->expect_exception(Unable_To_Delete_Directory::class);
        $this->adapter()->delete_directory('here');
    }
    /**
     * @test
     */
    public function setting_visibility_on_a_directory(): void
    {
        $adapter = $this->adapter();
        $adapter->create_directory('pri-dir', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PRIVATE]));
        $adapter->create_directory('pub-dir', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]));
        $this->expect_not_to_perform_assertions();
    }
    /**
     * @test
     */
    public function failing_to_move_a_file(): void
    {
        $this->given_we_have_an_existing_file('somewhere/here.txt');
        static::$archive_provider->stubbed_zip_archive()->fail_next_directory_creation();
        $this->expect_exception(Unable_To_Move_File::class);
        $this->adapter()->move('somewhere/here.txt', 'to-here/path.txt', new Config());
    }
    /**
     * @test
     */
    public function failing_to_copy_a_file(): void
    {
        $this->given_we_have_an_existing_file('here.txt');
        static::$archive_provider->stubbed_zip_archive()->fail_next_write();
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->adapter()->copy('here.txt', 'here.txt', new Config());
    }
    /**
     * @test
     */
    public function failing_to_set_visibility_because_the_file_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $this->adapter()->set_visibility('path.txt', Visibility::PUBLIC);
    }
    /**
     * @test
     */
    public function deleting_a_directory_with_files_in_it(): void
    {
        $this->given_we_have_an_existing_file('nested/path-a.txt');
        $this->given_we_have_an_existing_file('nested/path-b.txt');
        $this->adapter()->delete_directory('nested');
        $listing = iterator_to_array($this->adapter()->list_contents('', true));
        self::assert_equals([], $listing);
    }
    /**
     * @test
     */
    public function failing_to_set_visibility_because_setting_it_fails(): void
    {
        $this->given_we_have_an_existing_file('path.txt');
        static::$archive_provider->stubbed_zip_archive()->fail_when_setting_visibility();
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $this->adapter()->set_visibility('path.txt', Visibility::PUBLIC);
    }
    /**
     * @test
     *
     * @fixme Move to FilesystemAdapterTestCase once all adapters pass
     */
    public function moving_a_file_and_overwriting(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be moved', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->write('destination.txt', 'contents to be overwritten', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assert_false($adapter->file_exists('source.txt'), 'After moving a file should no longer exist in the original location.');
            $this->assert_true($adapter->file_exists('destination.txt'), 'After moving, a file should be present at the new location.');
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('contents to be moved', $adapter->read('destination.txt'));
        });
    }
    protected static function remove_zip_archive(): void
    {
        if (!file_exists(self::ARCHIVE)) {
            return;
        }
        unlink(self::ARCHIVE);
    }
}