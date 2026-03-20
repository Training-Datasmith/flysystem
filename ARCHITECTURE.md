# Flysystem — Architecture

## Purpose

Flysystem is a filesystem abstraction library for PHP. It provides a uniform interface for working with local disk, SFTP, S3, Azure Blob Storage, Google Cloud Storage, FTP, and in-memory filesystems, allowing application code to be storage-agnostic.

## Directory Structure

```
src/
  Filesystem.php                  — Primary API; implements FilesystemOperator
  Filesystem_Operator.php         — Combined reader+writer interface (extends both)
  Filesystem_Reader.php           — Read-side interface contract
  Filesystem_Writer.php           — Write-side interface contract
  Filesystem_Adapter.php          — Adapter contract (SPI boundary)
  Config.php                      — Immutable config bag passed to adapters
  Visibility.php                  — PUBLIC / PRIVATE constants
  Path_Normalizer.php             — Interface for path normalization strategies
  Path_Prefixer.php               — Prepends root prefix to paths for adapters
  Directory_Listing.php           — Lazy iterable of StorageAttributes
  Directory_Attributes.php        — Value object for directory entries
  File_Attributes.php             — Value object for file entries (size, mime, mtime)
  Mount_Manager.php               — Routes operations to multiple filesystems by prefix
  Calculate_Checksum_From_Stream.php — Fallback checksum when adapter lacks support

  Local/                          — Local filesystem adapter (PHP file functions)
  InMemory/                       — In-process array-backed adapter for testing
  Ftp/                            — FTP adapter (ftp_* functions)
  PhpseclibV2/ PhpseclibV3/       — SFTP adapters using phpseclib
  AwsS3V3/                        — AWS S3 adapter using aws-sdk-php
  AsyncAwsS3/                     — AWS S3 adapter using async-aws
  AzureBlobStorage/               — Azure Blob Storage adapter
  GoogleCloudStorage/             — Google Cloud Storage adapter
  GridFS/                         — MongoDB GridFS adapter
  PathPrefixing/                  — Decorator that prefixes all paths on another adapter

  AdapterTestUtilities/           — Shared PHPUnit test case for adapter compliance
```

## Key Design Decisions

### Adapter Pattern / SPI
Application code depends only on `Filesystem_Operator`. Storage-specific code lives entirely in `Filesystem_Adapter` implementations. Swapping backends requires changing one constructor argument.

### Immutable Config
`Config` is an immutable value object. `extend()` and `with_defaults()` return new instances, making config composition safe in concurrent contexts.

### Visibility as String Constants
`Visibility::PUBLIC` / `Visibility::PRIVATE` are string constants so they can be stored in databases and config files without mapping. Adapters convert them to backend-specific ACLs via `Visibility_Converter` helpers.

### Path Normalization at the Boundary
`Filesystem` normalizes every incoming path via `Path_Normalizer` before delegating to the adapter. This centralizes traversal-prevention and double-slash handling.

### Path Traversal Prevention
`Corrupted_Path_Detected` and `Path_Traversal_Detected` exceptions are thrown when a normalized path escapes the configured root. This is the primary security boundary.

### Lazy Directory Listing
`list_contents()` returns a `Directory_Listing` wrapping a `Generator`. Entries are yielded lazily; exceptions from the adapter are caught and rethrown as `Unable_To_List_Contents`.

## Extension Points

- **Custom adapter**: implement `Filesystem_Adapter` (14 methods)
- **Custom path normalizer**: implement `Path_Normalizer`
- **Custom visibility mapping**: implement `Visibility_Converter` (S3/GCS adapters)
- **Multiple filesystems**: use `Mount_Manager` with prefix routing (`s3://`, `local://`)
- **URL generation**: implement `Public_Url_Generator` or `Temporary_Url_Generator`

## Dependency Flow

```
Application
  → Filesystem::write($path, $content, $config)
       → Path_Normalizer::normalize_path($path)
       → Config::extend($config)          // merge per-call config
       → FilesystemAdapter::write($normalizedPath, $content, $mergedConfig)
            → Backend-specific I/O
```
