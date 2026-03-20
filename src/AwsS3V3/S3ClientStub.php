<?php

declare (strict_types=1);
namespace League\Flysystem\Aws_S3v3;

use Aws\Command;
use Aws\Command_Interface;
use Aws\Result_Interface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3client_Interface;
use Aws\S3\S3client_Trait;
use function Guzzle_Http\Promise\promise_for;
use Guzzle_Http\Psr7\Response;
use Throwable;
/**
 * @codeCoverageIgnore
 */
class S3client_Stub implements S3client_Interface
{
    use S3client_Trait;
    /**
     * @var S3ClientInterface
     */
    private $actual_client;
    /**
     * @var S3Exception[]
     */
    private array $staged_exceptions = [];
    /**
     * @var ResultInterface[]
     */
    private array $staged_result = [];
    private ?\Throwable $exception_for_upload = null;
    public function __construct(S3client_Interface $client)
    {
        return $this->actual_client = $client;
    }
    public function throw_during_upload(Throwable $throwable): void
    {
        $this->exception_for_upload = $throwable;
    }
    public function upload($bucket, $key, $body, $acl = 'private', array $options = [])
    {
        if ($this->exception_for_upload instanceof Throwable) {
            $throwable = $this->exception_for_upload;
            $this->exception_for_upload = null;
            throw $throwable;
        }
        return $this->actual_client->upload($bucket, $key, $body, $acl, $options);
    }
    public function fail_on_next_copy(): void
    {
        $this->throw_exception_when_executing_command('CopyObject');
    }
    public function throw_exception_when_executing_command(string $command_name, ?S3Exception $exception = null): void
    {
        $this->staged_exceptions[$command_name] = $exception ?? new S3Exception($command_name, new Command($command_name));
    }
    public function throw500exception_when_executing_command(string $command_name): void
    {
        $response = new Response(500);
        $exception = new S3Exception($command_name, new Command($command_name), compact('response'));
        $this->throw_exception_when_executing_command($command_name, $exception);
    }
    public function stage_result_for_command(string $command_name, Result_Interface $result): void
    {
        $this->staged_result[$command_name] = $result;
    }
    public function execute(Command_Interface $command)
    {
        return $this->execute_async($command)->wait();
    }
    public function get_command($name, array $args = [])
    {
        return $this->actual_client->get_command($name, $args);
    }
    public function get_handler_list()
    {
        return $this->actual_client->get_handler_list();
    }
    public function getIterator($name, array $args = [])
    {
        return $this->actual_client->getIterator($name, $args);
    }
    public function __call(string $name, array $arguments)
    {
        return $this->actual_client->__call($name, $arguments);
    }
    public function execute_async(Command_Interface $command)
    {
        $name = $command->get_name();
        if (array_key_exists($name, $this->staged_exceptions)) {
            $exception = $this->staged_exceptions[$name];
            unset($this->staged_exceptions[$name]);
            throw $exception;
        }
        if (array_key_exists($name, $this->staged_result)) {
            $result = $this->staged_result[$name];
            unset($this->staged_result[$name]);
            return promise_for($result);
        }
        return $this->actual_client->execute_async($command);
    }
    public function get_credentials()
    {
        return $this->actual_client->get_credentials();
    }
    public function get_region()
    {
        return $this->actual_client->get_region();
    }
    public function get_endpoint()
    {
        return $this->actual_client->get_endpoint();
    }
    public function get_api()
    {
        return $this->actual_client->get_api();
    }
    public function get_config($option = null)
    {
        return $this->actual_client->get_config($option);
    }
    public function get_paginator($name, array $args = [])
    {
        return $this->actual_client->get_paginator($name, $args);
    }
    public function wait_until($name, array $args = []): void
    {
        $this->actual_client->wait_until($name, $args);
    }
    public function get_waiter($name, array $args = [])
    {
        return $this->actual_client->get_waiter($name, $args);
    }
    public function create_presigned_request(Command_Interface $command, $expires, array $options = [])
    {
        return $this->actual_client->create_presigned_request($command, $expires, $options);
    }
    public function get_object_url($bucket, $key)
    {
        return $this->actual_client->get_object_url($bucket, $key);
    }
}