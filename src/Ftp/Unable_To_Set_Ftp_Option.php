<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
class Unable_To_Set_Ftp_Option extends RuntimeException implements Ftp_Connection_Exception
{
    public static function while_setting_option(string $option): Unable_To_Set_Ftp_Option
    {
        return new Unable_To_Set_Ftp_Option("Unable to set FTP option {$option}.");
    }
}