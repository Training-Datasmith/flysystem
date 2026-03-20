<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use function class_exists;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
use phpseclib3\Net\SFTP;
/**
 * @group sftp
 * @group phpseclib3
 */
class Sftp_Adapter_Test extends Filesystem_Adapter_Test_Case
{
    public static function set_up_before_class(): void
    {
        if (!class_exists(SFTP::class)) {
            self::mark_test_incomplete('No phpseclib v3 installed');
        }
    }
    private static ?\League\Flysystem\Phpseclib_V3\Stub_Sftp_Connection_Provider $connection_provider = null;
    /**
     * @var SftpStub
     */
    private $connection;
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        return new Sftp_Adapter(static::connection_provider(), '/upload');
    }
    /**
     * @before
     */
    public function setup_connection_provider(): void
    {
        /** @var SftpStub $connection */
        $connection = static::connection_provider()->provide_connection();
        $this->connection = $connection;
        $this->connection->reset_trip_wires();
    }
    /**
     * @test
     */
    public function failing_to_create_a_directory(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Create_Directory::class);
        $adapter->create_directory('not-gonna-happen', new Config());
    }
    /**
     * @test
     */
    public function failing_to_write_a_file(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('not-gonna-happen', 'na-ah', new Config());
    }
    /**
     * @test
     */
    public function failing_to_read_a_file(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Read_File::class);
        $adapter->read('not-gonna-happen');
    }
    /**
     * @test
     */
    public function failing_to_read_a_file_as_a_stream(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Read_File::class);
        $adapter->read_stream('not-gonna-happen');
    }
    /**
     * @test
     */
    public function failing_to_write_a_file_using_streams(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $write_handle = stream_with_contents('contents');
        $this->expect_exception(Unable_To_Write_File::class);
        try {
            $adapter->write_stream('not-gonna-happen', $write_handle, new Config());
        } finally {
            fclose($write_handle);
        }
    }
    /**
     * @test
     */
    public function detecting_mimetype(): void
    {
        $adapter = $this->adapter();
        $adapter->write('file.svg', (string) file_get_contents(__DIR__ . '/../AdapterTestUtilities/test_files/flysystem.svg'), new Config());
        $mime_type = $adapter->mime_type('file.svg');
        $this->assert_string_starts_with('image/svg+xml', $mime_type->mime_type());
    }
    /**
     * @test
     */
    public function failing_to_chmod_when_writing(): void
    {
        $this->connection->fail_on_chmod('/upload/path.txt');
        $adapter = $this->adapter();
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('path.txt', 'contents', new Config(['visibility' => 'public']));
    }
    /**
     * @test
     */
    public function failing_to_move_a_file_cause_the_parent_directory_cant_be_created(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Move_File::class);
        $adapter->move('path.txt', 'new-path.txt', new Config());
    }
    /**
     * @test
     */
    public function failing_to_copy_a_file(): void
    {
        $adapter = $this->adapter_with_invalid_root();
        $this->expect_exception(Unable_To_Copy_File::class);
        $adapter->copy('path.txt', 'new-path.txt', new Config());
    }
    /**
     * @test
     */
    public function failing_to_copy_a_file_because_writing_fails(): void
    {
        $this->given_we_have_an_existing_file('path.txt', 'contents');
        $adapter = $this->adapter();
        $this->connection->fail_on_put('/upload/new-path.txt');
        $this->expect_exception(Unable_To_Copy_File::class);
        $adapter->copy('path.txt', 'new-path.txt', new Config());
    }
    /**
     * @test
     */
    public function failing_to_chmod_when_writing_with_a_stream(): void
    {
        $write_stream = stream_with_contents('contents');
        $this->connection->fail_on_chmod('/upload/path.txt');
        $adapter = $this->adapter();
        $this->expect_exception(Unable_To_Write_File::class);
        try {
            $adapter->write_stream('path.txt', $write_stream, new Config(['visibility' => 'public']));
        } finally {
            @fclose($write_stream);
        }
    }
    /**
     * @test
     */
    public function list_contents_directory_does_not_exist(): void
    {
        $contents = $this->adapter()->list_contents('/does_not_exist', false);
        $this->assert_count(0, iterator_to_array($contents));
    }
    /**
     * @test
     */
    public function it_can_proactively_close_a_connection(): void
    {
        /** @var SftpAdapter $adapter */
        $adapter = $this->adapter();
        self::assert_false($adapter->file_exists('does not exists at all'));
        self::assert_true(static::$connection_provider->connection->is_connected());
        $adapter->disconnect();
        self::assert_false(static::$connection_provider->connection->is_connected());
    }
    /**
     * @test
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
    private static function connection_provider(): Stub_Sftp_Connection_Provider
    {
        if (!static::$connection_provider instanceof Connection_Provider) {
            static::$connection_provider = new Stub_Sftp_Connection_Provider('localhost', 'foo', 'pass', 2222);
        }
        return static::$connection_provider;
    }
    private function adapter_with_invalid_root(): Sftp_Adapter
    {
        $provider = static::connection_provider();
        return new Sftp_Adapter($provider, '/invalid');
    }
}