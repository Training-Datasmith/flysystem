<?php

declare (strict_types=1);
namespace League\Flysystem\Async_Aws_S3;

use Async_Aws\S3\Value_Object\Grant;
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
        if (Visibility::PUBLIC === $visibility) {
            return self::PUBLIC_ACL;
        }
        return self::PRIVATE_ACL;
    }
    /**
     * @param Grant[] $grants
     */
    public function acl_to_visibility(array $grants): string
    {
        foreach ($grants as $grant) {
            if (null === $grantee = $grant->get_grantee()) {
                continue;
            }
            $grantee_uri = $grantee->get_uri();
            $permission = $grant->get_permission();
            if (self::PUBLIC_GRANTEE_URI === $grantee_uri && self::PUBLIC_GRANTS_PERMISSION === $permission) {
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