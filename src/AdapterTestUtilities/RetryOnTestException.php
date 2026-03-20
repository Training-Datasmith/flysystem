<?php

declare (strict_types=1);
namespace League\Flysystem\Adapter_Test_Utilities;

use League\Flysystem\Filesystem_Exception;
use const PHP_EOL;
use const STDOUT;
use Throwable;
/**
 * @codeCoverageIgnore
 */
trait Retry_On_Test_Exception
{
    /**
     * @var string
     */
    protected $exception_type_to_retry_on;
    /**
     * @var int
     */
    protected $timeout_for_exception_retry = 2;
    protected function retry_on_exception(string $class_name, int $timout = 2): void
    {
        $this->exception_type_to_retry_on = $class_name;
        $this->timeout_for_exception_retry = $timout;
    }
    protected function retry_scenario_on_exception(string $class_name, callable $scenario, int $timeout = 2): void
    {
        $this->retry_on_exception($class_name, $timeout);
        $this->run_scenario($scenario);
    }
    protected function dont_retry_on_exception(): void
    {
        $this->exception_type_to_retry_on = null;
    }
    /**
     * @internal
     *
     * @throws Throwable
     */
    protected function run_setup(callable $scenario): void
    {
        $previous_exception = $this->exception_type_to_retry_on;
        $previous_timeout = $this->timeout_for_exception_retry;
        $this->retry_on_exception(Filesystem_Exception::class);
        try {
            $this->run_scenario($scenario);
        } finally {
            $this->exception_type_to_retry_on = $previous_exception;
            $this->timeout_for_exception_retry = $previous_timeout;
        }
    }
    protected function run_scenario(callable $scenario): void
    {
        if ($this->exception_type_to_retry_on === null) {
            $scenario();
            return;
        }
        $first_try_at = \time();
        $last_try_at = $first_try_at + 60;
        while (time() <= $last_try_at) {
            try {
                $scenario();
                return;
            } catch (Throwable $exception) {
                if (!$exception instanceof $this->exception_type_to_retry_on) {
                    throw $exception;
                }
                fwrite(STDOUT, 'Retrying ...' . PHP_EOL);
                sleep($this->timeout_for_exception_retry);
            }
        }
        $this->exception_type_to_retry_on = null;
        if (isset($exception) && $exception instanceof Throwable) {
            throw $exception;
        }
    }
}