<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/TestBase.php';

class EnergieverbrauchOptimiererStrategyOneTest extends TestCase
{
    protected function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our core stubs for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        parent::setUp();
    }
    public function testStrategyOneA(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 1000, 0, 1);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }

    public function testStrategyOneB(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 3000, 0, 1);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device1']));
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }

    public function testStrategyOneExact(): void
    {
        $test = setUpTest([1000, 1000, 1000, 1000, 1000], 5000, 0, 1);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device1']));
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device3']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));
        $this->assertTrue(GetValue($deviceIDs['Device5']));
    }

    public function testStrategyOneNegativeAvailable(): void
    {
        $test = setUpTest([1000, 1000, 1000, 1000, 1000], -100, 0, 1);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device2']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device4']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }
}