<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use RuntimeException;
use Throwable;
final class Unable_To_Resolve_Connection_Root extends RuntimeException implements Ftp_Connection_Exception
{
    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
    public static function it_does_not_exist(string $root, string $reason = ''): Unable_To_Resolve_Connection_Root
    {
        return new Unable_To_Resolve_Connection_Root('Unable to resolve connection root. It does not seem to exist: ' . $root . "\nreason: {$reason}");
    }
    public static function could_not_get_current_directory(string $message = ''): Unable_To_Resolve_Connection_Root
    {
        return new Unable_To_Resolve_Connection_Root('Unable to resolve connection root. Could not resolve the current directory. ' . $message);
    }
}