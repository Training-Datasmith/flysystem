<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use Google\Cloud\Core\Exception\Not_Found_Exception;
use Google\Cloud\Storage\Acl;
use Google\Cloud\Storage\Storage_Object;
use League\Flysystem\Visibility;
class Portable_Visibility_Handler implements Visibility_Handler
{
    public const NO_PREDEFINED_VISIBILITY = 'noPredefinedVisibility';
    public const ACL_PUBLIC_READ = 'publicRead';
    public const ACL_AUTHENTICATED_READ = 'authenticatedRead';
    public const ACL_PRIVATE = 'private';
    public const ACL_PROJECT_PRIVATE = 'projectPrivate';
    public function __construct(private string $entity = 'allUsers', private string $predefined_public_acl = self::ACL_PUBLIC_READ, private string $predefined_private_acl = self::ACL_PROJECT_PRIVATE)
    {
    }
    public function set_visibility(Storage_Object $object, string $visibility): void
    {
        if ($visibility === Visibility::PRIVATE) {
            $object->acl()->delete($this->entity);
        } elseif ($visibility === Visibility::PUBLIC) {
            $object->acl()->update($this->entity, Acl::ROLE_READER);
        }
    }
    public function determine_visibility(Storage_Object $object): string
    {
        try {
            $acl = $object->acl()->get(['entity' => 'allUsers']);
        } catch (Not_Found_Exception) {
            return Visibility::PRIVATE;
        }
        return $acl['role'] === Acl::ROLE_READER ? Visibility::PUBLIC : Visibility::PRIVATE;
    }
    public function visibility_to_predefined_acl(string $visibility): string
    {
        return match ($visibility) {
            Visibility::PUBLIC => $this->predefined_public_acl,
            self::NO_PREDEFINED_VISIBILITY => self::NO_PREDEFINED_VISIBILITY,
            default => $this->predefined_private_acl,
        };
    }
}