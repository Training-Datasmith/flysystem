<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
final class Unable_To_Connect_To_Ftp_Host extends RuntimeException implements Ftp_Connection_Exception
{
    public static function for_host(string $host, int $port, bool $ssl, string $reason = ''): Unable_To_Connect_To_Ftp_Host
    {
        $using_ssl = $ssl ? ', using ssl' : '';
        return new Unable_To_Connect_To_Ftp_Host("Unable to connect to host {$host} at port {$port}{$using_ssl}. {$reason}");
    }
}