<?php

declare(strict_types=1);
class Rucksackproblem extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('Source', 0);
        $this->RegisterPropertyInteger('BackpackSize', 0);
        $this->RegisterPropertyString('Consumers', '');
        $this->RegisterPropertyInteger('Tolerance', 0);
        $this->RegisterPropertyString('Strategy', '0');
        $this->RegisterPropertyString('Action', '0');
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
    }
}