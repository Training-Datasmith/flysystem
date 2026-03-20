<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use League\Flysystem\Adapter_Test_Utilities\Retry_On_Test_Exception;
use Php_Unit\Framework\Test_Case;
/**
 * @group ftp
 */
class Raw_List_Ftp_Connectivity_Checker_Test extends Test_Case
{
    use Retry_On_Test_Exception;
    /**
     * @test
     */
    public function detecting_if_a_connection_is_connected(): void
    {
        $this->retry_on_exception(Unable_To_Connect_To_Ftp_Host::class);
        $this->run_scenario(function (): void {
            $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2121, 'root' => '/home/foo/upload/', 'username' => 'foo', 'password' => 'pass']);
            $provider = new Ftp_Connection_Provider();
            $connection = $provider->create_connection($options);
            $connected_checker = new Raw_List_Ftp_Connectivity_Checker();
            $this->assert_true($connected_checker->is_connected($connection));
            @ftp_close($connection);
            $this->assert_false($connected_checker->is_connected($connection));
        });
    }
}