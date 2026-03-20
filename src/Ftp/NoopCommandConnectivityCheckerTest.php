<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use League\Flysystem\Adapter_Test_Utilities\Retry_On_Test_Exception;
use Php_Unit\Framework\Test_Case;
/**
 * @group ftp
 */
class Noop_Command_Connectivity_Checker_Test extends Test_Case
{
    use Retry_On_Test_Exception;
    protected function set_up(): void
    {
        $this->retry_on_exception(Unable_To_Connect_To_Ftp_Host::class);
    }
    /**
     * @test
     */
    public function detecting_a_good_connection(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        $connection = (new Ftp_Connection_Provider())->create_connection($options);
        $connected = (new Noop_Command_Connectivity_Checker())->is_connected($connection);
        $this->assert_true($connected);
    }
    /**
     * @test
     */
    public function detecting_a_closed_connection(): void
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'root' => '/home/foo/upload', 'username' => 'foo', 'password' => 'pass']);
        $this->run_scenario(function () use ($options): void {
            $connection = (new Ftp_Connection_Provider())->create_connection($options);
            ftp_close($connection);
            $connected = (new Noop_Command_Connectivity_Checker())->is_connected($connection);
            $this->assert_false($connected);
        });
    }
}