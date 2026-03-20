<?php

declare (strict_types=1);
namespace League\Flysystem\Async_Aws_S3;

use Async_Aws\S3\Value_Object\Grant;
interface Visibility_Converter
{
    public function visibility_to_acl(string $visibility): string;
    /**
     * @param Grant[] $grants
     */
    public function acl_to_visibility(array $grants): string;
    public function default_for_directories(): string;
}