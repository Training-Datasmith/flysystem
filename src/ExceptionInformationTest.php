<?php

declare (strict_types=1);
namespace League\Flysystem;

use Php_Unit\Framework\Test_Case;
/**
 * @group core
 */
class Exception_Information_Test extends Test_Case
{
    /**
     * @test
     */
    public function copy_exception_information(): void
    {
        $exception = Unable_To_Copy_File::from_location_to('from', 'to');
        $this->assert_equals('from', $exception->source());
        $this->assert_equals('to', $exception->destination());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_COPY, $exception->operation());
    }
    /**
     * @test
     */
    public function create_directory_exception_information(): void
    {
        $exception = Unable_To_Create_Directory::at_location('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_CREATE_DIRECTORY, $exception->operation());
    }
    /**
     * @test
     */
    public function delete_directory_exception_information(): void
    {
        $exception = Unable_To_Delete_Directory::at_location('from', 'some message');
        $this->assert_equals('some message', $exception->reason());
        $this->assert_equals('from', $exception->location());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_DELETE_DIRECTORY, $exception->operation());
    }
    /**
     * @test
     */
    public function delete_file_exception_information(): void
    {
        $exception = Unable_To_Delete_File::at_location('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_equals('some message', $exception->reason());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_DELETE, $exception->operation());
    }
    /**
     * @test
     */
    public function unable_to_check_for_file_existence(): void
    {
        $exception = Unable_To_Check_File_Existence::for_location('location');
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_FILE_EXISTS, $exception->operation());
    }
    /**
     * @test
     */
    public function unable_to_check_for_existence(): void
    {
        $exception = Unable_To_Check_Existence::for_location('location');
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_EXISTENCE_CHECK, $exception->operation());
    }
    /**
     * @test
     */
    public function unable_to_check_for_directory_existence(): void
    {
        $exception = Unable_To_Check_Directory_Existence::for_location('location');
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_DIRECTORY_EXISTS, $exception->operation());
    }
    /**
     * @test
     */
    public function move_file_exception_information(): void
    {
        $exception = Unable_To_Move_File::from_location_to('from', 'to');
        $this->assert_equals('from', $exception->source());
        $this->assert_equals('to', $exception->destination());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_MOVE, $exception->operation());
    }
    /**
     * @test
     */
    public function read_file_exception_information(): void
    {
        $exception = Unable_To_Read_File::from_location('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_equals('some message', $exception->reason());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_READ, $exception->operation());
    }
    /**
     * @test
     */
    public function retrieve_visibility_exception_information(): void
    {
        $exception = Unable_To_Retrieve_Metadata::visibility('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_equals(File_Attributes::ATTRIBUTE_VISIBILITY, $exception->metadata_type());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_RETRIEVE_METADATA, $exception->operation());
    }
    /**
     * @test
     */
    public function set_visibility_exception_information(): void
    {
        $exception = Unable_To_Set_Visibility::at_location('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_equals('some message', $exception->reason());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_SET_VISIBILITY, $exception->operation());
    }
    /**
     * @test
     */
    public function write_file_exception_information(): void
    {
        $exception = Unable_To_Write_File::at_location('from', 'some message');
        $this->assert_equals('from', $exception->location());
        $this->assert_equals('some message', $exception->reason());
        $this->assert_string_contains_string('some message', $exception->get_message());
        $this->assert_equals(Filesystem_Operation_Failed::OPERATION_WRITE, $exception->operation());
    }
    /**
     * @test
     */
    public function unreadable_file_exception_information(): void
    {
        $exception = Unreadable_File_Encountered::at_location('the-location');
        $this->assert_equals('the-location', $exception->location());
        $this->assert_string_contains_string('the-location', $exception->get_message());
    }
    /**
     * @test
     */
    public function symbolic_link_exception_information(): void
    {
        $exception = Symbolic_Link_Encountered::at_location('the-location');
        $this->assert_equals('the-location', $exception->location());
        $this->assert_string_contains_string('the-location', $exception->get_message());
    }
    /**
     * @test
     */
    public function path_traversal_exception_information(): void
    {
        $exception = Path_Traversal_Detected::for_path('../path.txt');
        $this->assert_equals('../path.txt', $exception->path());
        $this->assert_string_contains_string('../path.txt', $exception->get_message());
    }
}