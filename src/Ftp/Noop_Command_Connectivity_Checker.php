<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use TypeError;
use Value_Error;
class Noop_Command_Connectivity_Checker implements Connectivity_Checker
{
    public function is_connected($connection): bool
    {
        // @codeCoverageIgnoreStart
        try {
            $response = @ftp_raw($connection, 'NOOP');
        } catch (TypeError|Value_Error) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        $response_code = $response ? (int) preg_replace('/\D/', '', implode('', $response)) : false;
        return $response_code === 200;
    }
}