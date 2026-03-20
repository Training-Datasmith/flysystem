<?php

declare (strict_types=1);
namespace League\Flysystem;

use function hash_final;
use function hash_init;
use function hash_update_stream;
trait Calculate_Checksum_From_Stream
{
    private function calculate_checksum_from_stream(string $path, Config $config): string
    {
        try {
            $stream = $this->read_stream($path);
            $algo = (string) $config->get('checksum_algo', 'md5');
            $context = hash_init($algo);
            hash_update_stream($context, $stream);
            return hash_final($context);
        } catch (Filesystem_Exception $exception) {
            throw new Unable_To_Provide_Checksum($exception->get_message(), $path, $exception);
        }
    }
    /**
     * @return resource
     */
    abstract public function read_stream(string $path);
}