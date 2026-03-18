<?php

declare(strict_types=1);

include_once __DIR__ . '/../vendor/autoload.php';

function write_line(string $line)
{
    fwrite(STDOUT, "{$line}\n");
}

function panic(string $reason)
{
    write_line('🚨 ' . $reason);
    exit(1);
}
