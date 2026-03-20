<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use phpseclib\Net\SFTP;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\FixatedConnectivityChecker" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\FixatedConnectivityChecker" instead.
 */
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