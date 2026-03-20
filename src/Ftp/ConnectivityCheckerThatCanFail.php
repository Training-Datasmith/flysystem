<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

class Connectivity_Checker_That_Can_Fail implements Connectivity_Checker
{
    private bool $fail_next_call = false;
    public function __construct(private Connectivity_Checker $connectivity_checker)
    {
    }
    public function fail_next_call(): void
    {
        $this->fail_next_call = true;
    }
    /**
     * @inheritDoc
     */
    public function is_connected($connection): bool
    {
        if ($this->fail_next_call) {
            $this->fail_next_call = false;
            return false;
        }
        return $this->connectivity_checker->is_connected($connection);
    }
}