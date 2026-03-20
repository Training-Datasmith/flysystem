<?php

declare (strict_types=1);
namespace League\Flysystem\Adapter_Test_Utilities;

use DateInterval;
use DateTimeImmutable;
use function file_get_contents;
use Generator;
use function is_resource;
use function iterator_to_array;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\Directory_Attributes;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Provide_Checksum;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
use League\Flysystem\Visibility;
use const PHP_EOL;
use Php_Unit\Framework\Test_Case;
use Throwable;
/**
 * @codeCoverageIgnore
 */
abstract class Filesystem_Adapter_Test_Case extends Test_Case
{
    use Retry_On_Test_Exception;
    /**
     * @var FilesystemAdapter
     */
    protected static $adapter;
    /**
     * @var bool
     */
    protected $is_using_custom_adapter = false;
    public static function clear_filesystem_adapter_cache(): void
    {
        static::$adapter = null;
    }
    abstract protected static function create_filesystem_adapter(): Filesystem_Adapter;
    public function adapter(): Filesystem_Adapter
    {
        if (!static::$adapter instanceof Filesystem_Adapter) {
            static::$adapter = static::create_filesystem_adapter();
        }
        return static::$adapter;
    }
    public static function tear_down_after_class(): void
    {
        self::clear_filesystem_adapter_cache();
    }
    protected function set_up(): void
    {
        $this->adapter();
    }
    protected function use_adapter(Filesystem_Adapter $adapter): Filesystem_Adapter
    {
        static::$adapter = $adapter;
        $this->is_using_custom_adapter = true;
        return $adapter;
    }
    /**
     * @after
     */
    public function cleanup_adapter(): void
    {
        $this->clear_custom_adapter();
        $this->clear_storage();
    }
    public function clear_storage(): void
    {
        reset_function_mocks();
        try {
            $adapter = $this->adapter();
        } catch (Throwable) {
            /*
             * Setting up the filesystem adapter failed. This is OK at this stage.
             * The exception will have been shown to the user when trying to run
             * a test. We expect an exception to be thrown when tests are marked as
             * skipped when a filesystem adapter cannot be constructed.
             */
            return;
        }
        $this->run_setup(function () use ($adapter): void {
            /** @var StorageAttributes $item */
            foreach ($adapter->list_contents('', false) as $item) {
                if ($item->is_dir()) {
                    $adapter->delete_directory($item->path());
                } else {
                    $adapter->delete($item->path());
                }
            }
        });
    }
    public function clear_custom_adapter(): void
    {
        if ($this->is_using_custom_adapter) {
            $this->is_using_custom_adapter = false;
            self::clear_filesystem_adapter_cache();
        }
    }
    /**
     * @test
     */
    public function writing_and_reading_with_string(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'contents', new Config());
            $file_exists = $adapter->file_exists('path.txt');
            $contents = $adapter->read('path.txt');
            $this->assert_true($file_exists);
            $this->assert_equals('contents', $contents);
        });
    }
    /**
     * @test
     */
    public function writing_a_file_with_a_stream(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $write_stream = stream_with_contents('contents');
            $adapter->write_stream('path.txt', $write_stream, new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            if (is_resource($write_stream)) {
                fclose($write_stream);
            }
            $file_exists = $adapter->file_exists('path.txt');
            $this->assert_true($file_exists);
        });
    }
    /**
     * @test
     *
     * @dataProvider filenameProvider
     */
    public function writing_and_reading_files_with_special_path(string $path): void
    {
        $this->run_scenario(function () use ($path): void {
            $adapter = $this->adapter();
            $adapter->write($path, 'contents', new Config());
            $contents = $adapter->read($path);
            $this->assert_equals('contents', $contents);
        });
    }
    public static function filename_provider(): Generator
    {
        yield 'a path with square brackets in filename 1' => ['some/file[name].txt'];
        yield 'a path with square brackets in filename 2' => ['some/file[0].txt'];
        yield 'a path with square brackets in filename 3' => ['some/file[10].txt'];
        yield 'a path with square brackets in dirname 1' => ['some[name]/file.txt'];
        yield 'a path with square brackets in dirname 2' => ['some[0]/file.txt'];
        yield 'a path with square brackets in dirname 3' => ['some[10]/file.txt'];
        yield 'a path with curly brackets in filename 1' => ['some/file{name}.txt'];
        yield 'a path with curly brackets in filename 2' => ['some/file{0}.txt'];
        yield 'a path with curly brackets in filename 3' => ['some/file{10}.txt'];
        yield 'a path with curly brackets in dirname 1' => ['some{name}/filename.txt'];
        yield 'a path with curly brackets in dirname 2' => ['some{0}/filename.txt'];
        yield 'a path with curly brackets in dirname 3' => ['some{10}/filename.txt'];
        yield 'a path with space in dirname' => ['some dir/filename.txt'];
        yield 'a path with space in filename' => ['somedir/file name.txt'];
    }
    /**
     * @test
     */
    public function writing_a_file_with_an_empty_stream(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $write_stream = stream_with_contents('');
            $adapter->write_stream('path.txt', $write_stream, new Config());
            if (is_resource($write_stream)) {
                fclose($write_stream);
            }
            $file_exists = $adapter->file_exists('path.txt');
            $this->assert_true($file_exists);
            $contents = $adapter->read('path.txt');
            $this->assert_equals('', $contents);
        });
    }
    /**
     * @test
     */
    public function listing_a_directory_named_0(): void
    {
        $this->given_we_have_an_existing_file('0/path.txt');
        $this->given_we_have_an_existing_file('1/path.txt');
        $this->run_scenario(function (): void {
            $listing = iterator_to_array($this->adapter()->list_contents('0', false));
            $this->assert_count(1, $listing);
        });
    }
    /**
     * @test
     */
    public function reading_a_file(): void
    {
        $this->given_we_have_an_existing_file('path.txt', 'contents');
        $this->run_scenario(function (): void {
            $contents = $this->adapter()->read('path.txt');
            $this->assert_equals('contents', $contents);
        });
    }
    /**
     * @test
     */
    public function reading_a_file_with_a_stream(): void
    {
        $this->given_we_have_an_existing_file('path.txt', 'contents');
        $this->run_scenario(function (): void {
            $read_stream = $this->adapter()->read_stream('path.txt');
            $contents = stream_get_contents($read_stream);
            $this->assert_is_resource($read_stream);
            $this->assert_equals('contents', $contents);
            fclose($read_stream);
        });
    }
    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('path.txt', 'contents', ['visibility' => Visibility::PUBLIC]);
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config(['visibility' => Visibility::PRIVATE]));
            $contents = $adapter->read('path.txt');
            $this->assert_equals('new contents', $contents);
            $visibility = $adapter->visibility('path.txt')->visibility();
            $this->assert_equals(Visibility::PRIVATE, $visibility);
        });
    }
    /**
     * @test
     */
    public function a_file_exists_only_when_it_is_written_and_not_deleted(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            // does not exist before creation
            self::assert_false($adapter->file_exists('path.txt'));
            // a file exists after creation
            $this->given_we_have_an_existing_file('path.txt');
            self::assert_true($adapter->file_exists('path.txt'));
            // a file no longer exists after creation
            $adapter->delete('path.txt');
            self::assert_false($adapter->file_exists('path.txt'));
        });
    }
    /**
     * @test
     */
    public function listing_contents_shallow(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('some/0-path.txt', 'contents');
            $this->given_we_have_an_existing_file('some/1-nested/path.txt', 'contents');
            $listing = $this->adapter()->list_contents('some', false);
            /** @var StorageAttributes[] $items */
            $items = iterator_to_array($listing);
            $this->assert_instance_of(Generator::class, $listing);
            $this->assert_contains_only_instances_of(Storage_Attributes::class, $items);
            $this->assert_count(2, $items, $this->format_incorrect_listing_count($items));
            // Order of entries is not guaranteed
            [$file_index, $directory_index] = $items[0]->is_file() ? [0, 1] : [1, 0];
            $this->assert_equals('some/0-path.txt', $items[$file_index]->path());
            $this->assert_equals('some/1-nested', $items[$directory_index]->path());
            $this->assert_true($items[$file_index]->is_file());
            $this->assert_true($items[$directory_index]->is_dir());
        });
    }
    /**
     * @test
     */
    public function checking_if_a_non_existing_directory_exists(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            self::assert_false($adapter->directory_exists('this-does-not-exist.php'));
        });
    }
    /**
     * @test
     */
    public function checking_if_a_directory_exists_after_writing_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $this->given_we_have_an_existing_file('existing-directory/file.txt');
            self::assert_true($adapter->directory_exists('existing-directory'));
        });
    }
    /**
     * @test
     */
    public function checking_if_a_directory_exists_after_creating_it(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->create_directory('explicitly-created-directory', new Config());
            self::assert_true($adapter->directory_exists('explicitly-created-directory'));
            $adapter->delete_directory('explicitly-created-directory');
            $l = iterator_to_array($adapter->list_contents('/', false), false);
            self::assert_equals([], $l);
            self::assert_false($adapter->directory_exists('explicitly-created-directory'));
        });
    }
    /**
     * @test
     */
    public function listing_contents_recursive(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->create_directory('path', new Config());
            $adapter->write('path/file.txt', 'string', new Config());
            $listing = $adapter->list_contents('', true);
            /** @var StorageAttributes[] $items */
            $items = iterator_to_array($listing);
            $this->assert_count(2, $items, $this->format_incorrect_listing_count($items));
        });
    }
    protected function format_incorrect_listing_count(array $items): string
    {
        $message = "Incorrect number of items returned.\nThe listing contains:\n\n";
        /** @var StorageAttributes $item */
        foreach ($items as $item) {
            $message .= "- {$item->path()}\n";
        }
        return $message . PHP_EOL;
    }
    protected function given_we_have_an_existing_file(string $path, string $contents = 'contents', array $config = []): void
    {
        $this->run_setup(function () use ($path, $contents, $config): void {
            $this->adapter()->write($path, $contents, new Config($config));
        });
    }
    /**
     * @test
     */
    public function fetching_file_size(): void
    {
        $adapter = $this->adapter();
        $this->given_we_have_an_existing_file('path.txt', 'contents');
        $this->run_scenario(function () use ($adapter): void {
            $attributes = $adapter->file_size('path.txt');
            $this->assert_instance_of(File_Attributes::class, $attributes);
            $this->assert_equals(8, $attributes->file_size());
        });
    }
    /**
     * @test
     */
    public function setting_visibility(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $this->given_we_have_an_existing_file('path.txt', 'contents', [Config::OPTION_VISIBILITY => Visibility::PUBLIC]);
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('path.txt')->visibility());
            $adapter->set_visibility('path.txt', Visibility::PRIVATE);
            $this->assert_equals(Visibility::PRIVATE, $adapter->visibility('path.txt')->visibility());
            $adapter->set_visibility('path.txt', Visibility::PUBLIC);
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('path.txt')->visibility());
        });
    }
    /**
     * @test
     */
    public function fetching_file_size_of_a_directory(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = $this->adapter();
        $this->run_scenario(function () use ($adapter): void {
            $adapter->create_directory('path', new Config());
            $adapter->file_size('path/');
        });
    }
    /**
     * @test
     */
    public function fetching_file_size_of_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function (): void {
            $this->adapter()->file_size('non-existing-file.txt');
        });
    }
    /**
     * @test
     */
    public function fetching_last_modified_of_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function (): void {
            $this->adapter()->last_modified('non-existing-file.txt');
        });
    }
    /**
     * @test
     */
    public function fetching_visibility_of_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function (): void {
            $this->adapter()->visibility('non-existing-file.txt');
        });
    }
    /**
     * @test
     */
    public function fetching_the_mime_type_of_an_svg_file(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('file.svg', file_get_contents(__DIR__ . '/test_files/flysystem.svg'));
            $mimetype = $this->adapter()->mime_type('file.svg')->mime_type();
            $this->assert_string_starts_with('image/svg+xml', $mimetype);
        });
    }
    /**
     * @test
     */
    public function fetching_mime_type_of_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function (): void {
            $this->adapter()->mime_type('non-existing-file.txt');
        });
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->given_we_have_an_existing_file('unknown-mime-type.md5', file_get_contents(__DIR__ . '/test_files/unknown-mime-type.md5'));
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function (): void {
            $this->adapter()->mime_type('unknown-mime-type.md5');
        });
    }
    /**
     * @test
     */
    public function listing_a_toplevel_directory(): void
    {
        $this->given_we_have_an_existing_file('path1.txt');
        $this->given_we_have_an_existing_file('path2.txt');
        $this->run_scenario(function (): void {
            $contents = iterator_to_array($this->adapter()->list_contents('', true));
            $this->assert_count(2, $contents);
        });
    }
    /**
     * @test
     */
    public function writing_and_reading_with_streams(): void
    {
        $this->run_scenario(function (): void {
            $write_stream = stream_with_contents('contents');
            $adapter = $this->adapter();
            $adapter->write_stream('path.txt', $write_stream, new Config());
            if (is_resource($write_stream)) {
                fclose($write_stream);
            }
            $read_stream = $adapter->read_stream('path.txt');
            $this->assert_is_resource($read_stream);
            $contents = stream_get_contents($read_stream);
            fclose($read_stream);
            $this->assert_equals('contents', $contents);
        });
    }
    /**
     * @test
     */
    public function setting_visibility_on_a_file_that_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $this->run_scenario(function (): void {
            $this->adapter()->set_visibility('this-path-does-not-exists.txt', Visibility::PRIVATE);
        });
    }
    /**
     * @test
     */
    public function copying_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('text/plain', $adapter->mime_type('destination.txt')->mime_type());
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function copying_a_file_that_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->copy('source.txt', 'destination.txt', new Config());
        });
    }
    /**
     * @test
     */
    public function copying_a_file_again(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function moving_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assert_false($adapter->file_exists('source.txt'), 'After moving a file should no longer exist in the original location.');
            $this->assert_true($adapter->file_exists('destination.txt'), 'After moving, a file should be present at the new location.');
            $this->assert_equals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('text/plain', $adapter->mime_type('destination.txt')->mime_type());
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function file_exists_on_directory_is_false(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $this->assert_false($adapter->directory_exists('test'));
            $adapter->create_directory('test', new Config());
            $this->assert_true($adapter->directory_exists('test'));
            $this->assert_false($adapter->file_exists('test'));
        });
    }
    /**
     * @test
     */
    public function directory_exists_on_file_is_false(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $this->assert_false($adapter->file_exists('test.txt'));
            $adapter->write('test.txt', 'content', new Config());
            $this->assert_true($adapter->file_exists('test.txt'));
            $this->assert_false($adapter->directory_exists('test.txt'));
        });
    }
    /**
     * @test
     */
    public function reading_a_file_that_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->read('path.txt');
        });
    }
    /**
     * @test
     */
    public function moving_a_file_that_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Move_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->move('source.txt', 'destination.txt', new Config());
        });
    }
    /**
     * @test
     */
    public function trying_to_delete_a_non_existing_file(): void
    {
        $adapter = $this->adapter();
        $adapter->delete('path.txt');
        $file_exists = $adapter->file_exists('path.txt');
        $this->assert_false($file_exists);
    }
    /**
     * @test
     */
    public function checking_if_files_exist(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $file_exists_before = $adapter->file_exists('some/path.txt');
            $adapter->write('some/path.txt', 'contents', new Config());
            $file_exists_after = $adapter->file_exists('some/path.txt');
            $this->assert_false($file_exists_before);
            $this->assert_true($file_exists_after);
        });
    }
    /**
     * @test
     */
    public function fetching_last_modified(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'contents', new Config());
            $attributes = $adapter->last_modified('path.txt');
            $this->assert_instance_of(File_Attributes::class, $attributes);
            $this->assert_is_int($attributes->last_modified());
            $this->assert_true($attributes->last_modified() > time() - 30);
            $this->assert_true($attributes->last_modified() < time() + 30);
        });
    }
    /**
     * @test
     */
    public function failing_to_read_a_non_existing_file_into_a_stream(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read_stream('something.txt');
    }
    /**
     * @test
     */
    public function failing_to_read_a_non_existing_file(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $this->adapter()->read('something.txt');
    }
    /**
     * @test
     */
    public function creating_a_directory(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->create_directory('creating_a_directory/path', new Config());
            // Creating a directory should be idempotent.
            $adapter->create_directory('creating_a_directory/path', new Config());
            $contents = iterator_to_array($adapter->list_contents('creating_a_directory', false));
            $this->assert_count(1, $contents, $this->format_incorrect_listing_count($contents));
            /** @var DirectoryAttributes $directory */
            $directory = $contents[0];
            $this->assert_instance_of(Directory_Attributes::class, $directory);
            $this->assert_equals('creating_a_directory/path', $directory->path());
            $adapter->delete_directory('creating_a_directory/path');
        });
    }
    /**
     * @test
     */
    public function copying_a_file_with_collision(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $adapter->write('new-path.txt', 'contents', new Config());
            $adapter->copy('path.txt', 'new-path.txt', new Config());
            $contents = $adapter->read('new-path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    /**
     * @test
     */
    public function moving_a_file_with_collision(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $adapter->write('new-path.txt', 'contents', new Config());
            $adapter->move('path.txt', 'new-path.txt', new Config());
            $old_file_exists = $adapter->file_exists('path.txt');
            $this->assert_false($old_file_exists);
            $contents = $adapter->read('new-path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    /**
     * @test
     */
    public function copying_a_file_with_same_destination(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $adapter->copy('path.txt', 'path.txt', new Config());
            $contents = $adapter->read('path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    /**
     * @test
     */
    public function moving_a_file_with_same_destination(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $adapter->move('path.txt', 'path.txt', new Config());
            $contents = $adapter->read('path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    protected function assert_file_exists_at_path(string $path): void
    {
        $this->run_scenario(function () use ($path): void {
            $file_exists = $this->adapter()->file_exists($path);
            $this->assert_true($file_exists);
        });
    }
    /**
     * @test
     */
    public function generating_a_public_url(): void
    {
        $adapter = $this->adapter();
        if (!$adapter instanceof Public_Url_Generator) {
            $this->mark_test_skipped('Adapter does not supply public URls');
        }
        $adapter->write('some/path.txt', 'public contents', new Config(['visibility' => 'public']));
        $url = $adapter->public_url('some/path.txt', new Config());
        $contents = file_get_contents($url);
        self::assert_equals('public contents', $contents);
    }
    /**
     * @test
     */
    public function generating_a_temporary_url(): void
    {
        $adapter = $this->adapter();
        if (!$adapter instanceof Temporary_Url_Generator) {
            $this->mark_test_skipped('Adapter does not supply temporary URls');
        }
        $adapter->write('some/private.txt', 'public contents', new Config(['visibility' => 'private']));
        $expires_at = (new DateTimeImmutable())->add(DateInterval::create_from_date_string('1 minute'));
        $url = $adapter->temporary_url('some/private.txt', $expires_at, new Config());
        $contents = file_get_contents($url);
        self::assert_equals('public contents', $contents);
    }
    /**
     * @test
     */
    public function get_checksum(): void
    {
        $adapter = $this->adapter();
        if (!$adapter instanceof Checksum_Provider) {
            $this->mark_test_skipped('Adapter does not supply providing checksums');
        }
        $adapter->write('path.txt', 'foobar', new Config());
        $this->assert_same('3858f62230ac3c915f300c664312c63f', $adapter->checksum('path.txt', new Config()));
    }
    /**
     * @test
     */
    public function cannot_get_checksum_for_non_existent_file(): void
    {
        $adapter = $this->adapter();
        if (!$adapter instanceof Checksum_Provider) {
            $this->mark_test_skipped('Adapter does not supply providing checksums');
        }
        $this->expect_exception(Unable_To_Provide_Checksum::class);
        $adapter->checksum('path.txt', new Config());
    }
    /**
     * @test
     */
    public function cannot_get_checksum_for_directory(): void
    {
        $adapter = $this->adapter();
        if (!$adapter instanceof Checksum_Provider) {
            $this->mark_test_skipped('Adapter does not supply providing checksums');
        }
        $adapter->create_directory('dir', new Config());
        $this->expect_exception(Unable_To_Provide_Checksum::class);
        $adapter->checksum('dir', new Config());
    }
}