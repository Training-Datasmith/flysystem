<?php

declare (strict_types=1);
namespace League\Flysystem\Aws_S3v3;

use League\Flysystem\Visibility;
class Portable_Visibility_Converter implements Visibility_Converter
{
    private const PUBLIC_GRANTEE_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';
    private const PUBLIC_GRANTS_PERMISSION = 'READ';
    private const PUBLIC_ACL = 'public-read';
    private const PRIVATE_ACL = 'private';
    public function __construct(private string $default_for_directories = Visibility::PUBLIC)
    {
    }
    public function visibility_to_acl(string $visibility): string
    {
        if ($visibility === Visibility::PUBLIC) {
            return self::PUBLIC_ACL;
        }
        return self::PRIVATE_ACL;
    }
    public function acl_to_visibility(array $grants): string
    {
        foreach ($grants as $grant) {
            $grantee_uri = $grant['Grantee']['URI'] ?? null;
            $permission = $grant['Permission'] ?? null;
            if ($grantee_uri === self::PUBLIC_GRANTEE_URI && $permission === self::PUBLIC_GRANTS_PERMISSION) {
                return Visibility::PUBLIC;
            }
        }
        return Visibility::PRIVATE;
    }
    public function default_for_directories(): string
    {
        return $this->default_for_directories;
    }
}