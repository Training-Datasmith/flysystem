<?php

declare (strict_types=1);
namespace League\Flysystem\Local;

use function in_array;
use League\Mime_Type_Detection\Mime_Type_Detector;
class Fallback_Mime_Type_Detector implements Mime_Type_Detector
{
    private const INCONCLUSIVE_MIME_TYPES = ['application/x-empty', 'text/plain', 'text/x-asm', 'application/octet-stream', 'inode/x-empty'];
    public function __construct(private Mime_Type_Detector $detector, private array $inconclusive_mimetypes = self::INCONCLUSIVE_MIME_TYPES, private bool $use_inconclusive_mime_type_fallback = false)
    {
    }
    public function detect_mime_type(string $path, $contents): ?string
    {
        return $this->detector->detect_mime_type($path, $contents);
    }
    public function detect_mime_type_from_buffer(string $contents): ?string
    {
        return $this->detector->detect_mime_type_from_buffer($contents);
    }
    public function detect_mime_type_from_path(string $path): ?string
    {
        return $this->detector->detect_mime_type_from_path($path);
    }
    public function detect_mime_type_from_file(string $path): ?string
    {
        $mime_type = $this->detector->detect_mime_type_from_file($path);
        if ($mime_type !== null && !in_array($mime_type, $this->inconclusive_mimetypes)) {
            return $mime_type;
        }
        return $this->detector->detect_mime_type_from_path($path) ?? ($this->use_inconclusive_mime_type_fallback ? $mime_type : null);
    }
}