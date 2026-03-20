<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use Google\Cloud\Storage\Bucket;
use LogicException;
use Throwable;
class Stub_Rigged_Bucket extends Bucket
{
    private array $triggers = [];
    public function fail_for_object(string $name, ?Throwable $throwable = null): void
    {
        $this->setup_trigger('object', $name, $throwable);
    }
    public function fail_for_upload(string $name, ?Throwable $throwable = null): void
    {
        $this->setup_trigger('upload', $name, $throwable);
    }
    public function object($name, array $options = [])
    {
        $this->push_trigger('object', $name);
        return parent::object($name, $options);
    }
    public function upload($data, array $options = [])
    {
        $this->push_trigger('upload', $options['name'] ?? 'unknown-object-name');
        return parent::upload($data, $options);
    }
    private function setup_trigger(string $method, string $name, ?Throwable $throwable): void
    {
        $this->triggers[$method][$name] = $throwable ?? new LogicException('unknown error');
    }
    private function push_trigger(string $method, string $name): void
    {
        $trigger = $this->triggers[$method][$name] ?? null;
        if ($trigger instanceof Throwable) {
            unset($this->triggers[$method][$name]);
            throw $trigger;
        }
    }
}