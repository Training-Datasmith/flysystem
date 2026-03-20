<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
/**
 * @internal This is only used for testing purposes.
 */
class Sftp_Stub extends SFTP
{
    /**
     * @var array<string,bool>
     */
    private array $trip_wires = [];
    public function fail_on_chmod(string $filename): void
    {
        $key = $this->format_trip_key('chmod', $filename);
        $this->trip_wires[$key] = true;
    }
    /**
     * @param int    $mode
     * @param string $filename
     * @param bool   $recursive
     *
     * @return bool|mixed
     */
    public function chmod($mode, $filename, $recursive = false)
    {
        $key = $this->format_trip_key('chmod', $filename);
        $should_trip = $this->trip_wires[$key] ?? false;
        if ($should_trip) {
            unset($this->trip_wires[$key]);
            return false;
        }
        return parent::chmod($mode, $filename, $recursive);
    }
    public function fail_on_put(string $filename): void
    {
        $key = $this->format_trip_key('put', $filename);
        $this->trip_wires[$key] = true;
    }
    /**
     * @param string          $remote_file
     * @param resource|string $data
     * @param int             $mode
     * @param int             $start
     * @param int             $local_start
     *
     * @return bool
     */
    public function put($remote_file, $data, $mode = self::SOURCE_STRING, $start = -1, $local_start = -1, $progress_callback = null)
    {
        $key = $this->format_trip_key('put', $remote_file);
        $should_trip = $this->trip_wires[$key] ?? false;
        if ($should_trip) {
            return false;
        }
        return parent::put($remote_file, $data, $mode, $start, $local_start, $progress_callback);
    }
    /**
     * @param array<int,mixed> $arguments
     */
    private function format_trip_key(string ...$arguments): string
    {
        $key = '';
        foreach ($arguments as $argument) {
            $key .= var_export($argument, true);
        }
        return $key;
    }
    public function reset_trip_wires(): void
    {
        $this->trip_wires = [];
    }
}