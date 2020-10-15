<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/TestBase.php';

class EnergieverbrauchOptimiererStrategyTwoTest extends TestCase
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
    public function testStrategyTwoA(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 1000, 100, 2);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);
        //Status variable set properly
        $this->assertFalse(GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));
        $this->assertTrue(GetValue($deviceIDs['Device5']));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
    }

    public function testStrategyTwoB(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 3000, 100, 2);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Status variable set properly
        $this->assertFalse(GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device1']));
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));
        $this->assertTrue(GetValue($deviceIDs['Device5']));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device3']));
    }

    public function testStrategyTwoAError(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 1000, 0, 2);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Status variable set properly
        $this->assertTrue(GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device2']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device4']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }

    public function testStrategyTwoBError(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 3000, 0, 2);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);

        //Status variable set properly
        $this->assertTrue(GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device2']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device4']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }

    //Not enough consumers to reach available power
    public function testStrategyTwoNotEnough(): void
    {
        $test = setUpTest([2000, 750, 500, 200, 100], 10000, 100, 2);
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        EO_updateDevices($instanceID);
        //Status variable set properly
        $this->assertTrue(GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device1']));
        $this->assertFalse(GetValue($deviceIDs['Device2']));
        $this->assertFalse(GetValue($deviceIDs['Device3']));
        $this->assertFalse(GetValue($deviceIDs['Device4']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }
}