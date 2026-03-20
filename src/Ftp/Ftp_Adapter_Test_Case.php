<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use Generator;
use function iterator_to_array;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
/**
 * @group ftp
 *
 * @codeCoverageIgnore
 */
abstract class Ftp_Adapter_Test_Case extends Filesystem_Adapter_Test_Case
{
    protected function set_up(): void
    {
        parent::set_up();
        $this->retry_on_exception(Unable_To_Connect_To_Ftp_Host::class);
    }
    protected static Connectivity_Checker_That_Can_Fail $connectivity_checker;
    protected static ?Stub_Connection_Provider $connection_provider;
    /**
     * @after
     */
    public function reset_function_mocks(): void
    {
        reset_function_mocks();
    }
    public static function clear_filesystem_adapter_cache(): void
    {
        parent::clear_filesystem_adapter_cache();
        static::$connection_provider = null;
    }
    /**
     * @test
     */
    public function using_empty_string_for_root(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'root' => '', 'username' => 'foo', 'password' => 'pass']);
        $this->run_scenario(function () use ($options): void {
            $adapter = new Ftp_Adapter($options);
            $adapter->write('dirname1/dirname2/path.txt', 'contents', new Config());
            $adapter->write('dirname1/dirname2/path.txt', 'contents', new Config());
            $this->assert_true($adapter->file_exists('dirname1/dirname2/path.txt'));
            $this->assert_same('contents', $adapter->read('dirname1/dirname2/path.txt'));
        });
    }
    /**
     * @test
     */
    public function reconnecting_after_failure(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            static::$connectivity_checker->fail_next_call();
            $contents = iterator_to_array($adapter->list_contents('', false));
            $this->assert_is_array($contents);
        });
    }
    /**
     * @test
     *
     * @see https://github.com/thephpleague/flysystem/issues/1522
     */
    public function reading_a_file_twice_for_issue_1522(): void
    {
        $this->given_we_have_an_existing_file('some/nested/path.txt', 'this is it');
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            self::assert_equals('this is it', $adapter->read('some/nested/path.txt'));
            self::assert_equals('this is it', $adapter->read('some/nested/path.txt'));
            self::assert_equals('this is it', $adapter->read('some/nested/path.txt'));
        });
    }
    /**
     * @test
     *
     * @dataProvider scenariosCausingWriteFailure
     */
    public function failing_to_write_a_file(callable $scenario): void
    {
        $this->run_scenario(function () use ($scenario): void {
            $scenario();
        });
        $this->expect_exception(Unable_To_Write_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->write('some/path.txt', 'contents', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC, Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]));
        });
    }
    public static function scenarios_causing_write_failure(): Generator
    {
        yield 'Not being able to create the parent directory' => [function (): void {
            mock_function('ftp_mkdir', false);
        }];
        yield 'Not being able to set the parent directory visibility' => [function (): void {
            mock_function('ftp_chmod', false);
        }];
        yield 'Not being able to write the file' => [function (): void {
            mock_function('ftp_fput', false);
        }];
        yield 'Not being able to set the visibility' => [function (): void {
            mock_function('ftp_chmod', true, false);
        }];
    }
    /**
     * @test
     *
     * @dataProvider scenariosCausingDirectoryDeleteFailure
     */
    public function scenarios_causing_directory_deletion_to_fail(callable $scenario): void
    {
        $this->run_scenario($scenario);
        $this->given_we_have_an_existing_file('some/nested/path.txt');
        $this->expect_exception(Unable_To_Delete_Directory::class);
        $this->run_scenario(function (): void {
            $this->adapter()->delete_directory('some');
        });
    }
    public static function scenarios_causing_directory_delete_failure(): Generator
    {
        yield 'ftp_delete failure' => [function (): void {
            mock_function('ftp_delete', false);
        }];
        yield 'ftp_rmdir failure' => [function (): void {
            mock_function('ftp_rmdir', false);
        }];
    }
    /**
     * @test
     *
     * @dataProvider scenariosCausingCopyFailure
     */
    public function failing_to_copy(callable $scenario): void
    {
        $this->given_we_have_an_existing_file('path.txt');
        $scenario();
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->copy('path.txt', 'new/path.txt', new Config());
        });
    }
    /**
     * @test
     */
    public function failing_to_move_because_creating_the_directory_fails(): void
    {
        $this->given_we_have_an_existing_file('path.txt');
        mock_function('ftp_mkdir', false);
        $this->expect_exception(Unable_To_Move_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->move('path.txt', 'new/path.txt', new Config());
        });
    }
    public static function scenarios_causing_copy_failure(): Generator
    {
        yield 'failing to read' => [function (): void {
            mock_function('ftp_fget', false);
        }];
        yield 'failing to write' => [function (): void {
            mock_function('ftp_fput', false);
        }];
    }
    /**
     * @test
     */
    public function failing_to_delete_a_file(): void
    {
        $this->given_we_have_an_existing_file('path.txt', 'contents');
        mock_function('ftp_delete', false);
        $this->expect_exception(Unable_To_Delete_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->delete('path.txt');
        });
    }
    /**
     * @test
     */
    public function formatting_a_directory_listing_with_a_total_indicator(): void
    {
        $response = ['total 1', '-rw-r--r--   1 ftp      ftp           409 Aug 19 09:01 file1.txt'];
        mock_function('ftp_rawlist', $response);
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $contents = iterator_to_array($adapter->list_contents('/', false), false);
            $this->assert_count(1, $contents);
            $this->assert_contains_only_instances_of(File_Attributes::class, $contents);
        });
    }
    /**
     * @test
     *
     * @runInSeparateProcess
     */
    public function receiving_a_windows_listing(): void
    {
        $response = ['2015-05-23  12:09       <DIR>          dir1', '05-23-15  12:09PM                  684 file2.txt'];
        mock_function('ftp_rawlist', $response);
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $contents = iterator_to_array($adapter->list_contents('/', false), false);
            $this->assert_count(2, $contents);
            $this->assert_contains_only_instances_of(Storage_Attributes::class, $contents);
        });
    }
    /**
     * @test
     */
    public function receiving_an_invalid_windows_listing(): void
    {
        $response = ['05-23-15  12:09PM    file2.txt'];
        mock_function('ftp_rawlist', $response);
        $this->expect_exception(Invalid_List_Response_Received::class);
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            iterator_to_array($adapter->list_contents('/', false), false);
        });
    }
    /**
     * @test
     */
    public function getting_an_invalid_listing_response_for_unix_listings(): void
    {
        $response = ['total 1', '-rw-r--r--   1 ftp           409 Aug 19 09:01 file1.txt'];
        mock_function('ftp_rawlist', $response);
        $this->expect_exception(Invalid_List_Response_Received::class);
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            iterator_to_array($adapter->list_contents('/', false), false);
        });
    }
    /**
     * @test
     */
    public function failing_to_get_the_file_size_of_a_directory(): void
    {
        $adapter = $this->adapter();
        $this->run_scenario(function () use ($adapter): void {
            $adapter->create_directory('directory_name', new Config());
        });
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $this->run_scenario(function () use ($adapter): void {
            $adapter->file_size('directory_name');
        });
    }
    /**
     * @test
     */
    public function formatting_non_manual_recursive_listings(): void
    {
        $response = ['drwxr-xr-x   4 ftp      ftp          4096 Nov 24 13:58 .', 'drwxr-xr-x  16 ftp      ftp          4096 Sep  2 13:01 ..', 'drwxr-xr-x   2 ftp      ftp          4096 Oct 13  2012 cgi-bin', 'drwxr-xr-x   2 ftp      ftp          4096 Nov 24 13:59 folder', '-rw-r--r--   1 ftp      ftp           409 Oct 13  2012 index.html', '', 'somewhere/cgi-bin:', 'drwxr-xr-x   2 ftp      ftp          4096 Oct 13  2012 .', 'drwxr-xr-x   4 ftp      ftp          4096 Nov 24 13:58 ..', '', 'somewhere/folder:', 'drwxr-xr-x   2 ftp      ftp          4096 Nov 24 13:59 .', 'drwxr-xr-x   4 ftp      ftp          4096 Nov 24 13:58 ..', '-rw-r--r--   1 ftp      ftp             0 Nov 24 13:59 dummy.txt'];
        mock_function('ftp_rawlist', $response);
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'timestampsOnUnixListingsEnabled' => true, 'recurseManually' => false, 'root' => '/home/foo/upload/', 'username' => 'foo', 'password' => 'pass']);
        $this->run_scenario(function () use ($options): void {
            $adapter = new Ftp_Adapter($options);
            $contents = iterator_to_array($adapter->list_contents('somewhere', true), false);
            $this->assert_count(4, $contents);
            $this->assert_contains_only_instances_of(Storage_Attributes::class, $contents);
        });
    }
    /**
     * @test
     */
    public function filenames_and_dirnames_with_spaces_are_supported(): void
    {
        $this->given_we_have_an_existing_file('some dirname/file name.txt');
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $this->assert_true($adapter->file_exists('some dirname/file name.txt'));
            $contents = iterator_to_array($adapter->list_contents('', true));
            $this->assert_count(2, $contents);
            $this->assert_contains_only_instances_of(Storage_Attributes::class, $contents);
        });
    }
}