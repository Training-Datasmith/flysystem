<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
class Fixated_Connectivity_Checker implements Connectivity_Checker
{
    private int $number_of_times_checked = 0;
    public function __construct(private int $succeed_after = 0)
    {
    }
    public function is_connected(SFTP $connection): bool
    {
        if ($this->number_of_times_checked >= $this->succeed_after) {
            return true;
        }
        $this->number_of_times_checked++;
        return false;
    }
}