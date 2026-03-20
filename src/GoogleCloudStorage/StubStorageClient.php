<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use Google\Cloud\Storage\Storage_Client;
use function in_array;
class Stub_Storage_Client extends Storage_Client
{
    private ?Stub_Rigged_Bucket $rigged_bucket = null;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }
    /**
     * @var string|null
     */
    protected $project_id;
    public function bucket($name, $user_project = false, array $options = [])
    {
        $known_buckets = ['flysystem', 'no-acl-bucket-for-ci'];
        $is_known_bucket = in_array($name, $known_buckets);
        if ($is_known_bucket && !$this->rigged_bucket) {
            $this->rigged_bucket = new Stub_Rigged_Bucket($this->connection, $name, ['requesterProjectId' => $this->project_id]);
        }
        return $is_known_bucket ? $this->rigged_bucket : parent::bucket($name, $user_project);
    }
}