<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\UnableToEstablishAuthenticityOfHost" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\UnableToEstablishAuthenticityOfHost" instead.
 */
class Unable_To_Establish_Authenticity_Of_Host extends RuntimeException implements Filesystem_Exception
{
    public static function because_the_authenticity_cant_be_established(string $host): Unable_To_Establish_Authenticity_Of_Host
    {
        return new Unable_To_Establish_Authenticity_Of_Host("The authenticity of host {$host} can't be established.");
    }
}