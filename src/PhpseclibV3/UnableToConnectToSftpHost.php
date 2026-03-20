<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
use Throwable;
class Unable_To_Connect_To_Sftp_Host extends RuntimeException implements Filesystem_Exception
{
    public static function at_hostname(string $host, ?Throwable $previous = null): Unable_To_Connect_To_Sftp_Host
    {
        return new Unable_To_Connect_To_Sftp_Host("Unable to connect to host: {$host}", 0, $previous);
    }
}