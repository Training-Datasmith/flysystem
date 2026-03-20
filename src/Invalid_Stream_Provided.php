<?php

declare (strict_types=1);
namespace League\Flysystem;

use InvalidArgumentException as BaseInvalidArgumentException;
class Invalid_Stream_Provided extends Base_Invalid_Argument_Exception implements Filesystem_Exception
{
}