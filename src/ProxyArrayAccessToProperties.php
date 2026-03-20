<?php

declare (strict_types=1);
namespace League\Flysystem;

use RuntimeException;
/**
 * @internal
 */
trait Proxy_Array_Access_To_Properties
{
    private function format_property_name(string $offset): string
    {
        return str_replace('_', '', lcfirst(ucwords($offset, '_')));
    }
    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        $property = $this->format_property_name((string) $offset);
        return isset($this->{$property});
    }
    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        $property = $this->format_property_name((string) $offset);
        return $this->{$property};
    }
    /**
     * @param mixed $offset
     * @param mixed $value
     */
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Properties can not be manipulated');
    }
    /**
     * @param mixed $offset
     */
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Properties can not be manipulated');
    }
}