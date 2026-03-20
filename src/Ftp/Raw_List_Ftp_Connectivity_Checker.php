<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use Value_Error;
class Raw_List_Ftp_Connectivity_Checker implements Connectivity_Checker
{
    /**
     * @inheritDoc
     */
    public function is_connected($connection): bool
    {
        try {
            return $connection !== false && @ftp_rawlist($connection, './') !== false;
        } catch (Value_Error) {
            return false;
        }
    }
}