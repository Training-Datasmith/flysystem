<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
/**
 * @method void disconnect()
 */
interface Connection_Provider
{
    public function provide_connection(): SFTP;
}