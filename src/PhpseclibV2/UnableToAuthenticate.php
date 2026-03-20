<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\UnableToAuthenticate" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\UnableToAuthenticate" instead.
 */
class Unable_To_Authenticate extends RuntimeException implements Filesystem_Exception
{
    public static function with_password(): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using a password.');
    }
    public static function with_private_key(): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using a private key.');
    }
    public static function with_ssh_agent(): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using an SSH agent.');
    }
}