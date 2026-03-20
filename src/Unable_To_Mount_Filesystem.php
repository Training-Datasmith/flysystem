<?php

declare (strict_types=1);
namespace League\Flysystem;

use LogicException;
class Unable_To_Mount_Filesystem extends LogicException implements Filesystem_Exception
{
    /**
     * @param mixed $key
     */
    public static function because_the_key_is_not_valid($key): Unable_To_Mount_Filesystem
    {
        return new Unable_To_Mount_Filesystem('Unable to mount filesystem, key was invalid. String expected, received: ' . gettype($key));
    }
    /**
     * @param mixed $filesystem
     */
    public static function because_the_filesystem_was_not_valid($filesystem): Unable_To_Mount_Filesystem
    {
        $received = get_debug_type($filesystem);
        return new Unable_To_Mount_Filesystem('Unable to mount filesystem, filesystem was invalid. Instance of ' . Filesystem_Operator::class . ' expected, received: ' . $received);
    }
}