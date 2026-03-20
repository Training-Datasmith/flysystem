<?php

declare (strict_types=1);
namespace League\Flysystem;

class Unable_To_Check_Directory_Existence extends Unable_To_Check_Existence
{
    public function operation(): string
    {
        return Filesystem_Operation_Failed::OPERATION_DIRECTORY_EXISTS;
    }
}