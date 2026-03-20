<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use League\Flysystem\Filesystem_Adapter;
/**
 * @group ftpd
 */
class Ftpd_Adapter_Test extends Ftp_Adapter_Test_Case
{
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        $options = Ftp_Connection_Options::from_array(['host' => 'localhost', 'port' => 2122, 'timestampsOnUnixListingsEnabled' => true, 'root' => '/', 'username' => 'foo', 'password' => 'pass']);
        static::$connectivity_checker = new Connectivity_Checker_That_Can_Fail(new Noop_Command_Connectivity_Checker());
        return new Ftp_Adapter($options, null, static::$connectivity_checker);
    }
}