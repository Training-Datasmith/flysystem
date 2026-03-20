<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
final class Unable_To_Authenticate extends RuntimeException implements Ftp_Connection_Exception
{
    public function __construct()
    {
        parent::__construct('Unable to login/authenticate with FTP');
    }
}