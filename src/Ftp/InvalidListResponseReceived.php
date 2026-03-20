<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
class Invalid_List_Response_Received extends RuntimeException implements Filesystem_Exception
{
}