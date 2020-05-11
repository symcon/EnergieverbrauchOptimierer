<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class RucksackproblemValidationTest extends TestCaseSymconValidation
{
    public function testValidateRucksackproblem(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateRucksackproblemModule(): void
    {
        $this->validateModule(__DIR__ . '/../Rucksackproblem');
    }
}