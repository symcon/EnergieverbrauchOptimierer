<?php

declare(strict_types=1);

//Variabletypes
define('VARIABLETYPE_BOOLEAN', 0);
define('VARIABLETYPE_INTEGER', 1);
define('VARIABLETYPE_FLOAT', 2);

//Messages
define('VM_UPDATE', 10603);
include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

function setUpTest($values, $availableValue, $tolerance, $strategy)
{
    if (!IPS\ProfileManager::variableProfileExists('~Switch')) {
        IPS\ProfileManager::createVariableProfile('~Switch', 3);
        IPS\ProfileManager::createVariableProfile('~Switch', 0);
    }
    $instanceID = IPS_CreateInstance('{2E863560-5434-7166-C3F6-111D5D471A43}');

    $availableID = CreateVariable(VARIABLETYPE_INTEGER, false);
    SetValue($availableID, $availableValue);
    $devices = [];
    foreach ($values as $value) {
        $devices[] = ['Device' => CreateVariable(VARIABLETYPE_BOOLEAN, true), 'Usage' => $value];
    }

    //Configuration
    IPS_SetConfiguration($instanceID, json_encode(
    [
        'Source'    => $availableID,
        'Tolerance' => $tolerance,
        'Consumers' => json_encode($devices),
        'Strategy'  => $strategy
    ]
    ));

    IPS_ApplyChanges($instanceID);

    $deviceIDs = [];
    for ($i = 0; $i < count($devices); $i++) {
        $deviceIDs['Device' . ($i + 1)] = $devices[$i]['Device'];
    }
    return [
        'InstanceID'   => $instanceID,
        'DeviceIDs'    => $deviceIDs
    ];
}

function CreateVariable(int $VariableType, bool $action)
{
    $variableID = IPS_CreateVariable($VariableType);
    if ($action) {
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        IPS_SetVariableCustomAction($variableID, $scriptID);
    }
    return $variableID;
}