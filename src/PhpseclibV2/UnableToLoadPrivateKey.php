<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\UnableToLoadPrivateKey" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\UnableToLoadPrivateKey" instead.
 */
class Unable_To_Load_Private_Key extends RuntimeException implements Filesystem_Exception
{
    public function __construct(string $message = 'Unable to load private key.')
    {
        parent::__construct($message);
    }
}