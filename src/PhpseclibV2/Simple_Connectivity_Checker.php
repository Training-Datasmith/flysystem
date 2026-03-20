<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use phpseclib\Net\SFTP;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\SimpleConnectivityChecker" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\SimpleConnectivityChecker" instead.
 */
class Simple_Connectivity_Checker implements Connectivity_Checker
{
    public function is_connected(SFTP $connection): bool
    {
        return $connection->is_connected();
    }
}