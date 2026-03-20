<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use function ftp_close;
use League\Flysystem\Adapter_Test_Utilities\Retry_On_Test_Exception;
use Php_Unit\Framework\Test_Case;
/**
 * @group ftp
 */
class Ftp_Connection_Provider_Test extends Test_Case
{
    use Retry_On_Test_Exception;
    private ?\League\Flysystem\Ftp\Ftp_Connection_Provider $connection_provider = null;
    protected function set_up(): void
    {
        $this->retry_on_exception(Unable_To_Connect_To_Ftp_Host::class);
    }
    /**
     * @before
     */
    public function setup_connection_provider(): void
    {
        $this->connection_provider = new Ftp_Connection_Provider();
    }
    /**
     * @after
     */
    public function reset_function_mocks(): void
    {
        reset_function_mocks();
    }
    /**
     * @test
     */
    public function connecting_successfully(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'utf8' => true, 'passive' => true, 'ignorePassiveAddress' => true, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        $this->run_scenario(function () use ($options): void {
            $connection = $this->connection_provider->create_connection($options);
            $this->assert_true(ftp_close($connection));
        });
    }
    /**
     * @test
     */
    public function not_being_able_to_enable_uft8_mode(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'utf8' => true, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        mock_function('ftp_raw', ['Error']);
        $this->expect_exception(Unable_To_Enable_Utf8mode::class);
        $this->run_scenario(function () use ($options): void {
            $this->connection_provider->create_connection($options);
        });
    }
    /**
     * @test
     */
    public function uft8_mode_already_active_by_server(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'utf8' => true, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        mock_function('ftp_raw', ['202 UTF8 mode is always enabled. No need to send this command.']);
        $this->expect_not_to_perform_assertions();
        $this->run_scenario(function () use ($options): void {
            $this->connection_provider->create_connection($options);
        });
    }
    /**
     * @test
     */
    public function not_being_able_to_ignore_the_passive_address(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'ignorePassiveAddress' => true, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        mock_function('ftp_set_option', false);
        $this->expect_exception(Unable_To_Set_Ftp_Option::class);
        $this->run_scenario(function () use ($options): void {
            $this->connection_provider->create_connection($options);
        });
    }
    /**
     * @test
     */
    public function not_being_able_to_make_the_connection_passive(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'utf8' => true, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        mock_function('ftp_pasv', false);
        $this->expect_exception(Unable_To_Make_Connection_Passive::class);
        $this->run_scenario(function () use ($options): void {
            $this->connection_provider->create_connection($options);
        });
    }
    /**
     * @test
     */
    public function not_being_able_to_connect(): void
    {
        $this->dont_retry_on_exception();
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 313131, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        $this->expect_exception(Unable_To_Connect_To_Ftp_Host::class);
        $this->connection_provider->create_connection($options);
    }
    /**
     * @test
     */
    public function not_being_able_to_connect_over_ssl(): void
    {
        $this->dont_retry_on_exception();
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'ssl' => true, 'port' => 313131, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        $this->expect_exception(Unable_To_Connect_To_Ftp_Host::class);
        $this->connection_provider->create_connection($options);
    }
    /**
     * @test
     */
    public function not_being_able_to_authenticate(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'lolnope']);
        $this->expect_exception(Unable_To_Authenticate::class);
        $this->retry_on_exception(Unable_To_Connect_To_Ftp_Host::class);
        $this->run_scenario(function () use ($options): void {
            $this->connection_provider->create_connection($options);
        });
    }
}