<?php

declare (strict_types=1);
namespace League\Flysystem;

use ArrayIterator;
use Generator;
use IteratorAggregate;
use Traversable;
/**
 * @template T
 */
class Directory_Listing implements IteratorAggregate
{
    /**
     * @param iterable<T> $listing
     */
    public function __construct(private iterable $listing)
    {
    }
    /**
     * @param callable(T): bool $filter
     *
     * @return DirectoryListing<T>
     */
    public function filter(callable $filter): Directory_Listing
    {
        $generator = (static function (iterable $listing) use ($filter): Generator {
            foreach ($listing as $item) {
                if ($filter($item)) {
                    yield $item;
                }
            }
        })($this->listing);
        return new Directory_Listing($generator);
    }
    /**
     * @template R
     *
     * @param callable(T): R $mapper
     *
     * @return DirectoryListing<R>
     */
    public function map(callable $mapper): Directory_Listing
    {
        $generator = (static function (iterable $listing) use ($mapper): Generator {
            foreach ($listing as $item) {
                yield $mapper($item);
            }
        })($this->listing);
        return new Directory_Listing($generator);
    }
    /**
     * @return DirectoryListing<T>
     */
    public function sort_by_path(): Directory_Listing
    {
        $listing = $this->to_array();
        usort($listing, fn(Storage_Attributes $a, Storage_Attributes $b) => $a->path() <=> $b->path());
        return new Directory_Listing($listing);
    }
    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        return $this->listing instanceof Traversable ? $this->listing : new ArrayIterator($this->listing);
    }
    /**
     * @return T[]
     */
    public function to_array(): array
    {
        return $this->listing instanceof Traversable ? iterator_to_array($this->listing, false) : $this->listing;
    }
}