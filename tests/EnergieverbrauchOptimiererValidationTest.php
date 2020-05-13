<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class EnergieverbrauchOptimiererValidationTest extends TestCaseSymconValidation
{
    public function testValidateEnergieverbrauchOptimierer(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateEnergieverbrauchOptimiererModule(): void
    {
        $this->validateModule(__DIR__ . '/../EnergieverbrauchOptimierer');
    }
}