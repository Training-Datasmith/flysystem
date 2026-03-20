<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

interface Connectivity_Checker
{
    /**
     * @param resource $connection
     */
    public function is_connected($connection): bool;
}