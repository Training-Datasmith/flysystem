<?php

declare (strict_types=1);
namespace League\Flysystem;

use InvalidArgumentException;
use function var_export;
class Invalid_Visibility_Provided extends InvalidArgumentException implements Filesystem_Exception
{
    public static function with_visibility(string $visibility, string $expected_message): Invalid_Visibility_Provided
    {
        $provided = var_export($visibility, true);
        $message = "Invalid visibility provided. Expected {$expected_message}, received {$provided}";
        throw new Invalid_Visibility_Provided($message);
    }
}