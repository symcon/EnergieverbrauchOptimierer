<?php

declare(strict_types=1);
class EnergieverbrauchOptimierer extends IPSModule
{
    //Strategies
    const S_NEVER_TOO_MUCH = 1;
    const S_NEVER_TOO_LITTLE = 2;

    //Status
    const STATUS_OK = false;
    const STATUS_ERROR = true;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('Source', 0);
        $this->RegisterPropertyInteger('Tolerance', 0);
        $this->RegisterPropertyString('Consumers', '[]');
        $this->RegisterPropertyInteger('Strategy', self::S_NEVER_TOO_MUCH);

        //Profiles
        if (!IPS_VariableProfileExists('EO.Error')) {
            IPS_CreateVariableProfile('EO.Error', 0);
            IPS_SetVariableProfileIcon('EO.Error', 'Information');
            IPS_SetVariableProfileAssociation('EO.Error', 0/*self::STATUS_OK*/, $this->Translate('Ok'), '', 0x00FF00);
            IPS_SetVariableProfileAssociation('EO.Error', 1/*self::STATUS_ERROR*/, $this->Translate('Error'), '', 0xFF0000);
        }

        //Variables
        $this->RegisterVariableBoolean('Active', $this->Translate('Active'), '~Switch', 0);
        $this->EnableAction('Active');
        $this->RegisterVariableBoolean('Status', $this->Translate('Status'), 'EO.Error', 10);
        $this->RegisterVariableFloat('UsedPower', $this->Translate('Usage'));
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        //Return if instance is faulty
        $this->setInstanceStatus();
        if ($this->GetStatus() !== IS_ACTIVE) {
            return;
        }

        //Register messagee for source variable
        $sourceID = $this->ReadPropertyInteger('Source');
        $this->RegisterMessage($sourceID, VM_UPDATE);

        if ($this->GetValue('Active')) {
            $this->updateDevices();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data)
    {
        if ($this->GetValue('Active')) {
            if ($Data[0] != $Data[2]) {
                $this->updateDevices();
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                $this->SetValue($Ident, $Value);
                if (!$Value) {
                    //Deactivating all devices when deactivating the module
                    $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
                    foreach ($consumers as $consumer) {
                        if (HasAction($consumer['Device'])) {
                            RequestAction($consumer['Device'], false);
                        }
                    }
                } else {
                    $this->updateDevices();
                }
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Highlight faulty devices
        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
        foreach ($consumers as $consumer) {
            $deviceID = $consumer['Device'];
            $color = '';
            if (!IPS_VariableExists($deviceID) || !HasAction($deviceID) ||
                    (IPS_GetVariable($deviceID)['VariableType'] !== VARIABLETYPE_BOOLEAN)) {
                $color = '#FFC0C0';
            }
            $form['elements'][2]['values'][] = [
                'rowColor' => $color,
            ];
        }

        //Show/hide tolerance
        $form['elements'][3]['visible'] = boolval($this->ReadPropertyInteger('Strategy') === 2);

        return json_encode($form);
    }

    public function checkColumns($newConsumers)
    {
        $consumers = [];
        foreach ($newConsumers as $newConsumer) {
            $deviceID = $newConsumer['Device'];
            $color = '';
            if (!IPS_VariableExists($deviceID) || !HasAction($deviceID) ||
            (IPS_GetVariable($deviceID)['VariableType'] !== VARIABLETYPE_BOOLEAN)) {
                $color = '#FFC0C0';
            }
            $consumers[] = [
                'Device'   => $deviceID,
                'Usage'    => $newConsumer['Usage'],
                'rowColor' => $color
            ];
        }
        $this->UpdateFormField('Consumers', 'values', json_encode($consumers));
    }

    public function ToggleDisplayTolerance($strategy)
    {
        $this->UpdateFormField('Tolerance', 'visible', ($strategy === self::S_NEVER_TOO_LITTLE));
    }

    public function updateDevices()
    {
        $this->setInstanceStatus();
        if ($this->GetStatus() != IS_ACTIVE) {
            return;
        }

        $availablePower = GetValue($this->ReadPropertyInteger('Source'));
        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
        $strategy = $this->ReadPropertyInteger('Strategy');

        switch ($strategy) {
            case self::S_NEVER_TOO_MUCH: //default knapsack
                $capacity = $availablePower;
                break;

            case self::S_NEVER_TOO_LITTLE: //Inverse knapsack
                $totalUsage = 0;
                foreach ($consumers as $consumer) {
                    $totalUsage += $consumer['Usage'];
                }
                $capacity = $totalUsage - $availablePower;
                break;

            default:
                echo 'unknown strategy';
                return [];
        }
        if ($capacity < 0) {
            $this->SetValue('Status', self::STATUS_ERROR);
            $this->SetValue('UsedPower', 0);
            //Disable all devices
            $this->switchDevices([]);
            $this->SendDebug('Error', 'Capacity negative', 0);
            return;
        }

        $deviceIDs = [];
        $usage = [];
        foreach ($consumers as $consumer) {
            $deviceIDs[] = $consumer['Device'];
            $usage[] = $consumer['Usage'];
        }

        $pickedIndex = $this->KnapSack($capacity, $usage);

        //Create return array containing devices which should be switched
        $switchDevices = [];
        $switchValue = 0;
        foreach ($pickedIndex as $index) {
            $switchDevices[] = $deviceIDs[$index];
        }
        //If we use strategy 2 the knapsack returns which devices should be turned off, so we need to invert the switch devices
        if ($strategy === self::S_NEVER_TOO_LITTLE) {
            $switchDevices = array_diff($deviceIDs, $switchDevices);
        }
        foreach ($switchDevices as $switchDevice) {
            $switchValue += $usage[array_search($switchDevice, $deviceIDs)];
        }

        //Only when using strategy 2 we need to reach the given power limit including tolerance
        $tolerance = $this->ReadPropertyInteger('Tolerance');
        if (($strategy == self::S_NEVER_TOO_LITTLE) && $switchValue > ($availablePower + $tolerance)) {
            $this->SetValue('Status', self::STATUS_ERROR);
            $this->SetValue('UsedPower', 0);
            //Disable all devies
            $this->switchDevices([]);
            $this->SendDebug('Error', 'Power can not be used completely', 0);
            return;
        } else {
            $this->SetValue('Status', self::STATUS_OK);
        }
        $this->SetValue('UsedPower', $switchValue);
        //Switch devices
        $this->switchDevices($switchDevices);
    }

    //Knapsack problem = https://en.wikipedia.org/wiki/Knapsack_problem
    private function KnapSack($capacity, $weights)
    {
        $K = [];

        for ($i = 0; $i <= count($weights); ++$i) {
            for ($w = 0; $w <= $capacity; ++$w) {
                if ($i == 0 || $w == 0) {
                    $K[$i][$w] = 0;
                    $K['picked'][$i][$w] = [];
                } elseif ($weights[$i - 1] <= $w) {
                    $withMe = $weights[$i - 1] + $K[$i - 1][$w - $weights[$i - 1]];
                    $withoutMe = $K[$i - 1][$w];
                    if ($withMe > $withoutMe) {
                        $K[$i][$w] = $withMe;
                        $K['picked'][$i][$w] = $K['picked'][$i - 1][$w - $weights[$i - 1]];
                        $K['picked'][$i][$w][] = $i - 1;
                    } else {
                        $K[$i][$w] = $withoutMe;
                        $K['picked'][$i][$w] = $K['picked'][$i - 1][$w];
                    }
                } else {
                    $K[$i][$w] = $K[$i - 1][$w];
                    $K['picked'][$i][$w] = $K['picked'][$i - 1][$w];
                }
            }
        }
        return $K['picked'][count($weights)][$capacity];
    }

    private function setInstanceStatus()
    {
        $getInstanceStatus = function ()
        {
            $sourceID = $this->ReadPropertyInteger('Source');
            if ($sourceID === 0) {
                return IS_INACTIVE;
            }
            if (!IPS_VariableExists($sourceID)) {
                return 200;
            }
            $variableType = IPS_GetVariable($sourceID)['VariableType'];
            if (($variableType !== VARIABLETYPE_INTEGER) && ($variableType !== VARIABLETYPE_FLOAT)) {
                return 201;
            }

            $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
            if (empty($consumers)) {
                return 204;
            }
            foreach ($consumers as $consumer) {
                $deviceID = $consumer['Device'];
                if (!IPS_VariableExists($deviceID)) {
                    return 203;
                }
                if (IPS_GetVariable($deviceID)['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                    return 202;
                }
                if (!HasAction($deviceID)) {
                    return 203;
                }
            }

            return IS_ACTIVE;
        };

        $this->SetStatus($getInstanceStatus());
    }

    private function switchDevices($switchDevices)
    {
        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
        foreach ($consumers as $consumer) {
            $deviceIDs[] = $consumer['Device'];
        }
        foreach ($deviceIDs as $deviceID) {
            if (in_array($deviceID, $switchDevices)) {
                RequestAction($deviceID, true);
            } else {
                RequestAction($deviceID, false);
            }
        }
    }
}