<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
use Throwable;
class Unable_To_Load_Private_Key extends RuntimeException implements Filesystem_Exception
{
    public function __construct(?string $message = 'Unable to load private key.', ?Throwable $previous = null)
    {
        parent::__construct($message ?? 'Unable to load private key.', 0, $previous);
    }
}