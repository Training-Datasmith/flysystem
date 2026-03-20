<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\UnableToConnectToSftpHost" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\UnableToConnectToSftpHost" instead.
 */
class Unable_To_Connect_To_Sftp_Host extends RuntimeException implements Filesystem_Exception
{
    public static function at_hostname(string $host): Unable_To_Connect_To_Sftp_Host
    {
        return new Unable_To_Connect_To_Sftp_Host("Unable to connect to host: {$host}");
    }
}