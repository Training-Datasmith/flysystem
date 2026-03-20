<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use phpseclib\Net\SFTP;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\ConnectionProvider" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\ConnectionProvider" instead.
 */
interface Connection_Provider
{
    public function provide_connection(): SFTP;
}