<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
use Throwable;
class Simple_Connectivity_Checker implements Connectivity_Checker
{
    public function __construct(private bool $use_ping = false)
    {
    }
    public static function create(): Simple_Connectivity_Checker
    {
        return new Simple_Connectivity_Checker();
    }
    public function with_using_ping(bool $use_ping): Simple_Connectivity_Checker
    {
        $clone = clone $this;
        $clone->use_ping = $use_ping;
        return $clone;
    }
    public function is_connected(SFTP $connection): bool
    {
        if (!$connection->is_connected()) {
            return false;
        }
        if (!$this->use_ping) {
            return true;
        }
        try {
            return $connection->ping();
        } catch (Throwable) {
            return false;
        }
    }
}