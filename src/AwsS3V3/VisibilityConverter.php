<?php

declare (strict_types=1);
namespace League\Flysystem\Aws_S3v3;

interface Visibility_Converter
{
    public function visibility_to_acl(string $visibility): string;
    public function acl_to_visibility(array $grants): string;
    public function default_for_directories(): string;
}