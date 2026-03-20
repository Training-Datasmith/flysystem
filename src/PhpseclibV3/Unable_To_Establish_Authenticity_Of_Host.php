<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
class Unable_To_Establish_Authenticity_Of_Host extends RuntimeException implements Filesystem_Exception
{
    public static function because_the_authenticity_cant_be_established(string $host): Unable_To_Establish_Authenticity_Of_Host
    {
        return new Unable_To_Establish_Authenticity_Of_Host("The authenticity of host {$host} can't be established.");
    }
}