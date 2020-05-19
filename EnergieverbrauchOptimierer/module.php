<?php

declare(strict_types=1);
class EnergieverbrauchOptimierer extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('Source', 0);
        $this->RegisterPropertyInteger('Tolerance', 0);
        $this->RegisterPropertyString('Consumers', '');
        $this->RegisterPropertyString('Strategy', '1');

        //Profiles
        if (!IPS_VariableProfileExists('EO.Error')) {
            IPS_CreateVariableProfile('EO.Error', 0);
            IPS_SetVariableProfileIcon('EO.Error', 'Information');
            IPS_SetVariableProfileAssociation('EO.Error', 1, $this->Translate('Error'), '', 0xFF0000);
            IPS_SetVariableProfileAssociation('EO.Error', 0, $this->Translate('Ok'), '', 0x00FF00);
        }

        //Variables
        $this->RegisterVariableBoolean('Active', $this->Translate('Active'), '~Switch', 0);
        $this->EnableAction('Active');
        $this->RegisterVariableBoolean('Error', $this->Translate('Error'), 'EO.Error', 10);
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

        $this->setInstanceStatus();
        if ($this->GetStatus() != 102) {
            return;
        }
        //Unregister messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        $sourceID = $this->ReadPropertyInteger('Source');
        $this->RegisterMessage($sourceID, VM_UPDATE);

        if ($this->ReadPropertyString('Strategy') == '1') {
            IPS_SetHidden($this->GetIDForIdent('Error'), true);
        } else {
            IPS_SetHidden($this->GetIDForIdent('Error'), false);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data)
    {
        if ($this->GetValue('Active')) {
            if ($Data[0] != $Data[2]) {
                $this->switchDevices($this->calculateActiveDevices());
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                $this->SetValue($Ident, $Value);
                if (!$Value) {
                    $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
                    foreach ($consumers as $consumer) {
                        RequestAction($consumer['Device'], false);
                    }
                } else {
                    $this->switchDevices($this->calculateActiveDevices());
                }
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    public function calculateActiveDevices()
    {
        $this->setInstanceStatus();
        if ($this->GetStatus() != 102) {
            return [];
        }

        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
        $availablePower = GetValue($this->ReadPropertyInteger('Source'));
        $tolerance = $this->ReadPropertyInteger('Tolerance');
        $strategy = $this->ReadPropertyString('Strategy');

        switch ($strategy) {
            case '1': //+Tolerance Knapsack
                $values = [];
                $capacity = $availablePower + $tolerance;
                foreach ($consumers as $consumer) {
                    $devices[] = $consumer['Device'];
                    $values[] = $consumer['Usage'];
                }

                $pickedIndex = $this->KnapSack($capacity, $values);

            break;
            case '2': //-Tolerance
                echo 'Not implemented yet';
            break;
        }

        //Create return array containing devices which should be switched
        $activeDevices = [];
        $consumedValue = 0;
        foreach ($pickedIndex as $index) {
            $activeDevices[] = $devices[$index];
            $consumedValue += $values[$index];
        }
        $this->SetValue('UsedPower', $consumedValue);
        return $activeDevices;
    }

    public function switchDevices($devices)
    {
        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);
        foreach ($consumers as $consumer) {
            if (in_array($consumer['Device'], $devices)) {
                RequestAction($consumer['Device'], true);
            } else {
                RequestAction($consumer['Device'], false);
            }
        }
    }

    public function KnapSack($capacity, $weight)
    {
        $K = [];

        for ($i = 0; $i <= count($weight); ++$i) {
            for ($w = 0; $w <= $capacity; ++$w) {
                if ($i == 0 || $w == 0) {
                    $K[$i][$w] = 0;
                    $K['picked'][$i][$w] = [];
                } elseif ($weight[$i - 1] <= $w) {
                    $withMe = $weight[$i - 1] + $K[$i - 1][$w - $weight[$i - 1]];
                    $withoutMe = $K[$i - 1][$w];
                    if ($withMe > $withoutMe) {
                        $K[$i][$w] = $withMe;
                        $K['picked'][$i][$w] = $K['picked'][$i - 1][$w - $weight[$i - 1]];
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
        return $K['picked'][count($weight)][$capacity];
    }

    private function setInstanceStatus()
    {
        $sourceID = $this->ReadPropertyInteger('Source');

        //Check source variable
        if ($sourceID == 0) {
            $this->SetStatus(104);
            return;
        }
        if (IPS_VariableExists($sourceID)) {
            switch (IPS_GetVariable($sourceID)['VariableType']) {
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    break;
                default:
                    $this->SetStatus(201);
                    return;
                    break;

            }
        } else {
            $this->SetStatus(200);
            return;
        }

        $consumers = json_decode($this->ReadPropertyString('Consumers'), true);

        foreach ($consumers as $consumer) {
            $deviceID = $consumer['Device'];
            //Checking weather variable exists and is boolean and set status accordingly
            if (IPS_VariableExists($deviceID)) {
                switch (IPS_GetVariable($deviceID)['VariableType']) {
                    case VARIABLETYPE_BOOLEAN:
                        break;
                    default:
                        $this->SetStatus(202);
                        return;
                        break;

                }
            }

            if (!HasAction($deviceID)) {
                $this->SetStatus(203);
                return;
            }
        }

        $this->SetStatus(102);
        return;
    }
}