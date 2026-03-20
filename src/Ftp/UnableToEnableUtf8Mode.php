<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
final class Unable_To_Enable_Utf8mode extends RuntimeException implements Ftp_Connection_Exception
{
}