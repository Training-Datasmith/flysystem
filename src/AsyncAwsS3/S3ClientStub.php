<?php

declare (strict_types=1);
namespace League\Flysystem\Async_Aws_S3;

use Async_Aws\Core\Exception\Exception;
use Async_Aws\Core\Exception\Http\Network_Exception;
use Async_Aws\Core\Result;
use Async_Aws\S3\Input\Copy_Object_Request;
use Async_Aws\S3\Input\Delete_Object_Request;
use Async_Aws\S3\Input\Delete_Objects_Request;
use Async_Aws\S3\Input\Get_Object_Acl_Request;
use Async_Aws\S3\Input\Get_Object_Request;
use Async_Aws\S3\Input\Head_Object_Request;
use Async_Aws\S3\Input\List_Objects_V2request;
use Async_Aws\S3\Input\Put_Object_Acl_Request;
use Async_Aws\S3\Input\Put_Object_Request;
use Async_Aws\S3\Result\Copy_Object_Output;
use Async_Aws\S3\Result\Delete_Object_Output;
use Async_Aws\S3\Result\Delete_Objects_Output;
use Async_Aws\S3\Result\Get_Object_Acl_Output;
use Async_Aws\S3\Result\Get_Object_Output;
use Async_Aws\S3\Result\Head_Object_Output;
use Async_Aws\S3\Result\List_Objects_V2output;
use Async_Aws\S3\Result\Object_Exists_Waiter;
use Async_Aws\S3\Result\Put_Object_Acl_Output;
use Async_Aws\S3\Result\Put_Object_Output;
use Async_Aws\S3\S3Client;
use Async_Aws\Simple_S3\Simple_S3client;
use DateTimeImmutable;
use Symfony\Component\Http_Client\Mock_Http_Client;
/**
 * @codeCoverageIgnore
 */
class S3client_Stub extends Simple_S3client
{
    /**
     * @var S3Client
     */
    private $actual_client;
    /**
     * @var Exception[]
     */
    private array $staged_exceptions = [];
    /**
     * @var Result[]
     */
    private array $staged_result = [];
    public function __construct(Simple_S3client $client, $configuration = [])
    {
        $this->actual_client = $client;
        parent::__construct($configuration, null, new Mock_Http_Client());
    }
    public function throw_exception_when_executing_command(string $command_name, ?Exception $exception = null): void
    {
        $this->staged_exceptions[$command_name] = $exception ?? new Network_Exception();
    }
    public function stage_result_for_command(string $command_name, Result $result): void
    {
        $this->staged_result[$command_name] = $result;
    }
    private function get_staged_result(string $name): ?Result
    {
        if (array_key_exists($name, $this->staged_exceptions)) {
            $exception = $this->staged_exceptions[$name];
            unset($this->staged_exceptions[$name]);
            throw $exception;
        }
        if (array_key_exists($name, $this->staged_result)) {
            $result = $this->staged_result[$name];
            unset($this->staged_result[$name]);
            return $result;
        }
        return null;
    }
    /**
     * @param array|CopyObjectRequest $input
     */
    public function copy_object($input): Copy_Object_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('CopyObject') ?? $this->actual_client->copy_object($input);
    }
    /**
     * @param array|DeleteObjectRequest $input
     */
    public function delete_object($input): Delete_Object_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('DeleteObject') ?? $this->actual_client->delete_object($input);
    }
    /**
     * @param array|HeadObjectRequest $input
     */
    public function head_object($input): Head_Object_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('HeadObject') ?? $this->actual_client->head_object($input);
    }
    /**
     * @param array|HeadObjectRequest $input
     */
    public function object_exists($input): Object_Exists_Waiter
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('ObjectExists') ?? $this->actual_client->object_exists($input);
    }
    /**
     * @param array|ListObjectsV2Request $input
     */
    public function list_objects_v2($input): List_Objects_V2output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('ListObjectsV2') ?? $this->actual_client->list_objects_v2($input);
    }
    /**
     * @param array|DeleteObjectsRequest $input
     */
    public function delete_objects($input): Delete_Objects_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('DeleteObjects') ?? $this->actual_client->delete_objects($input);
    }
    /**
     * @param array|GetObjectAclRequest $input
     */
    public function get_object_acl($input): Get_Object_Acl_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('GetObjectAcl') ?? $this->actual_client->get_object_acl($input);
    }
    /**
     * @param array|PutObjectAclRequest $input
     */
    public function put_object_acl($input): Put_Object_Acl_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('PutObjectAcl') ?? $this->actual_client->put_object_acl($input);
    }
    /**
     * @param array|PutObjectRequest $input
     */
    public function put_object($input): Put_Object_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('PutObject') ?? $this->actual_client->put_object($input);
    }
    /**
     * @param array|GetObjectRequest $input
     */
    public function get_object($input): Get_Object_Output
    {
        // @phpstan-ignore-next-line
        return $this->get_staged_result('GetObject') ?? $this->actual_client->get_object($input);
    }
    public function get_url(string $bucket, string $key): string
    {
        return $this->actual_client->get_url($bucket, $key);
    }
    public function get_presigned_url(string $bucket, string $key, ?DateTimeImmutable $expires = null, ?string $version_id = null): string
    {
        return $this->actual_client->get_presigned_url($bucket, $key, $expires);
    }
}