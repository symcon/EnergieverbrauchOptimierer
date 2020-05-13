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
                $numcalls = 0;
                $m = [];
                $pickedIndex = [];

                list($resultUsage, $pickedIndex) = $this->knapSolveFast($values, $values, count($values) - 1, $capacity, $m);

                break;
            case '2': //-Tolerance

                $values = [];
                $capacity = $availablePower - $tolerance;
                foreach ($consumers as $consumer) {
                    $devices[] = $consumer['Device'];
                    $values[] = $consumer['Usage'];
                }

                //Initialize
                $numcalls = 0;
                $m = [];
                $pickedIndex = [];

                list($resultUsage, $pickedIndex) = $this->knapSolveFast($values, $values, count($values) - 1, $capacity, $m);
                if ($resultUsage != $capacity) {
                    $this->SetValue('Error', true);
                    return [];
                } else {
                    $this->SetValue('Error', false);
                }
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

    public function knapSolveFast($w, $v, $i, $aW, &$m) //https://rosettacode.org/wiki/Knapsack_problem/0-1#PHP
    {
        global $numcalls;
        $numcalls++;
        // echo "Called with i=$i, aW=$aW<br>";

        // Return memo if we have one
        if (isset($m[$i][$aW])) {
            return [$m[$i][$aW], $m['picked'][$i][$aW]];
        } else {

            // At end of decision branch
            if ($i == 0) {
                if ($w[$i] <= $aW) { // Will this item fit?
                    $m[$i][$aW] = $v[$i]; // Memo this item
                    $m['picked'][$i][$aW] = [$i]; // and the picked item
                    return [$v[$i], [$i]]; // Return the value of this item and add it to the picked list
                } else {
                    // Won't fit
                    $m[$i][$aW] = 0; // Memo zero
                    $m['picked'][$i][$aW] = []; // and a blank array entry...
                    return [0, []]; // Return nothing
                }
            }

            // Not at end of decision branch..
            // Get the result of the next branch (without this one)
            list($without_i, $without_PI) = $this->knapSolveFast($w, $v, $i - 1, $aW, $m);

            if ($w[$i] > $aW) { // Does it return too many?

                $m[$i][$aW] = $without_i; // Memo without including this one
                $m['picked'][$i][$aW] = $without_PI; // and a blank array entry...
                return [$without_i, $without_PI]; // and return it
            } else {

                // Get the result of the next branch (WITH this one picked, so available weight is reduced)
                list($with_i, $with_PI) = $this->knapSolveFast($w, $v, ($i - 1), ($aW - $w[$i]), $m);
                $with_i += $v[$i];  // ..and add the value of this one..

                // Get the greater of WITH or WITHOUT
                if ($with_i > $without_i) {
                    $res = $with_i;
                    $picked = $with_PI;
                    array_push($picked, $i);
                } else {
                    $res = $without_i;
                    $picked = $without_PI;
                }

                $m[$i][$aW] = $res; // Store it in the memo
                $m['picked'][$i][$aW] = $picked; // and store the picked item
                return [$res, $picked]; // and then return it
            }
        }
    }
}