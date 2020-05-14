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
        $this->RegisterVariableBoolean('Error', $this->Translate('Error'), 'EO.Error', 0);
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

        //Unregister messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        $sourceID = $this->ReadPropertyInteger('Source');

        //Register message if source is valid
        if ($sourceID != 0 && IPS_VariableExists($sourceID)) {
            switch (IPS_GetVariable($sourceID)['VariableType']) {
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    $this->RegisterMessage($sourceID, VM_UPDATE);
                    break;
                default:
                    $this->SetStatus(200);
                    return;
                    break;

            }
        } else {
            //Status inactive
            $this->SetStatus(104);
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
                        $this->SetStatus(201);
                        return;
                        break;

                }
            }

            if (!HasAction($deviceID)) {
                $this->SetStatus(202);
                return;
            }
        }

        if ($this->ReadPropertyString('Strategy') == '1') {
            IPS_SetHidden($this->GetIDForIdent('Error'), true);
        } else {
            IPS_SetHidden($this->GetIDForIdent('Error'), false);
        }

        $this->SetStatus(102);
    }

    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data)
    {
        $this->SendDebug('MessageSink', json_encode($Data), 0);
        if ($Data[0] != $Data[2]) {
            $this->SendDebug('MessageSink', 'not equal', 0);
            $this->switchDevices($this->calculateActiveDevices());
        }
    }

    public function calculateActiveDevices()
    {
        if ($this->GetStatus() != 102) {
            $this->SendDebug('calculateActiveDevices', 'status not ok', 0);
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

                //Initialize
               $pickedIndex = $this->KnapSack($availablePower, $values);

                break;
            case '2': //-Tolerance

                break;
        }

        //Create return array containing devices which should be switched
        $activeDevices = [];
        foreach ($pickedIndex as $index) {
            $activeDevices[] = $devices[$index];
        }

        return $activeDevices;
    }

    public function switchDevices($devices)
    {
        $this->SendDebug('switchDevices', 'triggered', 0);
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
                    $K['picked'][$i][$w][] = $K['picked'][$i - 1][$w];
                }
            }
        }
        return $K['picked'][count($weight)][$capacity];
    }
}