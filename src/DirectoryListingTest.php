<?php

declare (strict_types=1);
namespace League\Flysystem;

use Generator;
use function iterator_to_array;
use Php_Unit\Framework\Test_Case;
/**
 * @group core
 */
class Directory_Listing_Test extends Test_Case
{
    /**
     * @test
     */
    public function mapping_a_listing(): void
    {
        $numbers = $this->generate_integers(1, 10);
        $listing = new Directory_Listing($numbers);
        $mapped_listing = $listing->map(fn(int $i) => $i * 2);
        $mapped_numbers = $mapped_listing->to_array();
        $expected_numbers = [2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
        $this->assert_equals($expected_numbers, $mapped_numbers);
    }
    /**
     * @test
     */
    public function mapping_a_listing_twice(): void
    {
        $numbers = $this->generate_integers(1, 10);
        $listing = new Directory_Listing($numbers);
        $mapped_listing = $listing->map(fn(int $i) => $i * 2);
        $mapped_listing = $mapped_listing->map(fn(int $i) => $i / 2);
        $mapped_numbers = $mapped_listing->to_array();
        $expected_numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $this->assert_equals($expected_numbers, $mapped_numbers);
    }
    /**
     * @test
     */
    public function filter_a_listing(): void
    {
        $numbers = $this->generate_integers(1, 20);
        $listing = new Directory_Listing($numbers);
        $filered_listing = $listing->filter(fn(int $i) => $i % 2 === 0);
        $mapped_numbers = $filered_listing->to_array();
        $expected_numbers = [2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
        $this->assert_equals($expected_numbers, $mapped_numbers);
    }
    /**
     * @test
     */
    public function filter_a_listing_twice(): void
    {
        $numbers = $this->generate_integers(1, 20);
        $listing = new Directory_Listing($numbers);
        $filtered_listing = $listing->filter(fn(int $i) => $i % 2 === 0);
        $filtered_listing = $filtered_listing->filter(fn(int $i) => $i > 10);
        $mapped_numbers = $filtered_listing->to_array();
        $expected_numbers = [12, 14, 16, 18, 20];
        $this->assert_equals($expected_numbers, $mapped_numbers);
    }
    /**
     * @test
     */
    public function sorting_a_directory_listing(): void
    {
        $expected = ['a/a/a.txt', 'b/c/a.txt', 'c/b/a.txt', 'c/c/a.txt'];
        $listing = new Directory_Listing([new File_Attributes('b/c/a.txt'), new File_Attributes('c/c/a.txt'), new File_Attributes('c/b/a.txt'), new File_Attributes('a/a/a.txt')]);
        $actual = $listing->sort_by_path()->map(fn($i) => $i->path())->to_array();
        self::assert_equals($expected, $actual);
    }
    /**
     * @test
     *
     * @description this ensures that the output of a sorted listing is iterable
     *
     * @see https://github.com/thephpleague/flysystem/issues/1342
     */
    public function iterating_over_storted_output(): void
    {
        $listing = new Directory_Listing([new File_Attributes('b/c/a.txt'), new File_Attributes('c/c/a.txt'), new File_Attributes('c/b/a.txt'), new File_Attributes('a/a/a.txt')]);
        self::expect_not_to_perform_assertions();
        iterator_to_array($listing->sort_by_path());
    }
    /**
     * @return Generator<int>
     */
    private function generate_integers(int $min, int $max): Generator
    {
        for ($i = $min; $i <= $max; $i++) {
            yield $i;
        }
    }
}