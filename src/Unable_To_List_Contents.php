<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
use Throwable;
final class Unable_To_List_Contents extends RuntimeException implements Filesystem_Operation_Failed
{
    public static function at_location(string $location, bool $deep, Throwable $previous): Unable_To_List_Contents
    {
        $message = "Unable to list contents for '{$location}', " . ($deep ? 'deep' : 'shallow') . " listing\n\n" . 'Reason: ' . $previous->get_message();
        return new Unable_To_List_Contents($message, 0, $previous);
    }
    public function operation(): string
    {
        return self::OPERATION_LIST_CONTENTS;
    }
}