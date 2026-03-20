<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use League\Flysystem\Filesystem_Exception;
use RuntimeException;
class Unable_To_Authenticate extends RuntimeException implements Filesystem_Exception
{
    public function __construct(string $message, private ?string $connection_error = null)
    {
        parent::__construct($message);
    }
    public static function with_password(?string $last_error = null): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using a password.', $last_error);
    }
    public static function with_private_key(?string $last_error = null): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using a private key.', $last_error);
    }
    public static function with_ssh_agent(?string $last_error = null): Unable_To_Authenticate
    {
        return new Unable_To_Authenticate('Unable to authenticate using an SSH agent.', $last_error);
    }
    public function connection_error(): ?string
    {
        return $this->connection_error;
    }
}