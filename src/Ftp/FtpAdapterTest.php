<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use League\Flysystem\Filesystem_Adapter;
use function mock_function;
use function reset_function_mocks;
/**
 * @group ftp
 */
class Ftp_Adapter_Test extends Ftp_Adapter_Test_Case
{
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'timestampsOnUnixListingsEnabled' => true, 'root' => '/home/foo/upload/', 'username' => 'foo', 'password' => 'pass']);
        static::$connectivity_checker = new Connectivity_Checker_That_Can_Fail(new Noop_Command_Connectivity_Checker());
        static::$connection_provider = new Stub_Connection_Provider(new Ftp_Connection_Provider());
        return new Ftp_Adapter($options, static::$connection_provider, static::$connectivity_checker);
    }
    /**
     * @test
     */
    public function disconnect_after_destruct(): void
    {
        /** @var FtpAdapter $adapter */
        $adapter = $this->adapter();
        $reflection = new \Reflection_Object($adapter);
        $adapter->file_exists('foo.txt');
        $reflection_property = $reflection->get_property('connection');
        $reflection_property->set_accessible(true);
        $connection = $reflection_property->get_value($adapter);
        unset($reflection);
        $this->assert_true(false !== ftp_pwd($connection));
        $adapter->__destruct();
        static::clear_filesystem_adapter_cache();
        $this->assert_false((new Noop_Command_Connectivity_Checker())->is_connected($connection));
    }
    /**
     * @test
     */
    public function it_can_disconnect(): void
    {
        /** @var FtpAdapter $adapter */
        $adapter = $this->adapter();
        $this->assert_false($adapter->file_exists('not-existing.file'));
        self::assert_true(static::$connectivity_checker->is_connected(static::$connection_provider->connection));
        $adapter->disconnect();
        self::assert_false(static::$connectivity_checker->is_connected(static::$connection_provider->connection));
    }
    /**
     * @test
     */
    public function not_being_able_to_resolve_connection_root(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'timestampsOnUnixListingsEnabled' => true, 'root' => '/invalid/root', 'username' => 'foo', 'password' => 'pass']);
        $adapter = new Ftp_Adapter($options);
        $this->expect_exception_object(Unable_To_Resolve_Connection_Root::it_does_not_exist('/invalid/root'));
        $adapter->delete('something');
    }
    /**
     * @test
     */
    public function not_being_able_to_resolve_connection_root_pwd(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'timestampsOnUnixListingsEnabled' => true, 'root' => '/home/foo/upload/', 'username' => 'foo', 'password' => 'pass']);
        $this->expect_exception_object(Unable_To_Resolve_Connection_Root::could_not_get_current_directory());
        mock_function('ftp_pwd', false);
        $adapter = new Ftp_Adapter($options);
        $adapter->delete('something');
    }
    protected function tear_down(): void
    {
        reset_function_mocks();
    }
}