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
        $test = setUpTest([2000, 750, 500, 200, 100], 1000, 0, '1');
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        $activeDevices = EO_calculateActiveDevices($instanceID);
        EO_switchDevices($instanceID, $activeDevices);

        // Check calculated result
        $this->assertEquals($activeDevices, [$deviceIDs['Device2'], $deviceIDs['Device4']]);

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
        $test = setUpTest([2000, 750, 500, 200, 100], 3000, 0, '1');
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        $activeDevices = EO_calculateActiveDevices($instanceID);
        EO_switchDevices($instanceID, $activeDevices);

        // Check calculated result
        $this->assertEquals($activeDevices, [$deviceIDs['Device1'], $deviceIDs['Device2'], $deviceIDs['Device4']]);

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
        $start = microtime(true);
        $test = setUpTest([1000, 1000, 1000, 1000, 1000], 5000, 0, '1');
        $end = microtime(true);
        echo "\n" . ($end - $start) . "\n";
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        $activeDevices = EO_calculateActiveDevices($instanceID);
        EO_switchDevices($instanceID, $activeDevices);

        var_dump($activeDevices);
        // Check calculated result
        $this->assertEquals($activeDevices, [$deviceIDs['Device1'], $deviceIDs['Device2'], $deviceIDs['Device3'], $deviceIDs['Device4'], $deviceIDs['Device5']]);

        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device1']));
        $this->assertTrue(GetValue($deviceIDs['Device2']));
        $this->assertTrue(GetValue($deviceIDs['Device3']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));
        $this->assertTrue(GetValue($deviceIDs['Device5']));
    }

    public function testStrategyOneTolerance(): void
    {
        $start = microtime(true);
        $test = setUpTest([100, 250, 700, 1000, 500], 1300, 500, '1');
        $end = microtime(true);
        echo "\n" . ($end - $start) . "\n";
        $instanceID = $test['InstanceID'];
        $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
        $deviceIDs = $test['DeviceIDs'];
        $this->assertEquals(102, $instanceStatus);
        $activeDevices = EO_calculateActiveDevices($instanceID);
        EO_switchDevices($instanceID, $activeDevices);

        var_dump($activeDevices);
        var_dump($deviceIDs);

        // Check calculated result
        $this->assertEquals($activeDevices, [$deviceIDs['Device1'], $deviceIDs['Device3'], $deviceIDs['Device4']]);

        //Check enabled devices
        $this->assertTrue(GetValue($deviceIDs['Device1']));
        $this->assertTrue(GetValue($deviceIDs['Device3']));
        $this->assertTrue(GetValue($deviceIDs['Device4']));

        //Check disabled devices
        $this->assertFalse(GetValue($deviceIDs['Device2']));
        $this->assertFalse(GetValue($deviceIDs['Device5']));
    }

    // public function testStrategyTwoA(): void
    // {
    //     $test = setUpTest([2000, 750, 500, 200, 100], 1000, 0, '2');
    //     $instanceID = $test['InstanceID'];
    //     $instanceStatus = IPS_GetInstance($instanceID)['InstanceStatus'];
    //     $deviceIDs = $test['DeviceIDs'];
    //     $this->assertEquals(102, $instanceStatus);
    //     $activeDevices = EO_calculateActiveDevices($instanceID);
    //     EO_switchDevices($instanceID, $activeDevices);

    //     // Check calculated result
    //     $this->assertEquals($activeDevices, [$deviceIDs['Device2'], $deviceIDs['Device4']]);

    //     //Check enabled devices
    //     $this->assertTrue(GetValue($deviceIDs['Device2']));
    //     $this->assertTrue(GetValue($deviceIDs['Device4']));

    //     //Check disabled devices
    //     $this->assertFalse(GetValue($deviceIDs['Device1']));
    //     $this->assertFalse(GetValue($deviceIDs['Device3']));
    //     $this->assertFalse(GetValue($deviceIDs['Device5']));
    // }
}