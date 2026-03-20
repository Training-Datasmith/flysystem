<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
class Unable_To_Make_Connection_Passive extends RuntimeException implements Ftp_Connection_Exception
{
}