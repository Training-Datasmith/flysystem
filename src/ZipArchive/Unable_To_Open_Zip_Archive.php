<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use RuntimeException;
final class Unable_To_Open_Zip_Archive extends RuntimeException implements Zip_Archive_Exception
{
    public static function at_location(string $location, string $reason = ''): self
    {
        return new self(rtrim(sprintf('Unable to open file at location: %s. %s', $location, $reason)));
    }
}