<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
interface Connectivity_Checker
{
    public function is_connected(SFTP $connection): bool;
}