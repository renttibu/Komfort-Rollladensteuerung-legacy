<?php

// Declare
declare(strict_types=1);

trait KRS_messageSink
{
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->ReadPropertyBoolean('UseMessageSinkDebug')) {
            $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
            if (!empty($Data)) {
                foreach ($Data as $key => $value) {
                    $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
                }
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                // Actuator blind position
                $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Die Rollladenposition hat sich geändert.', 0);
                            $this->UpdateBlindPosition();
                        }
                    }
                }
                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckDoorWindowSensors();
                        }
                    }
                }
                // Sunrise
                $sunrise = $this->ReadPropertyInteger('Sunrise');
                if ($sunrise != 0 && @IPS_ObjectExists($sunrise)) {
                    if ($SenderID == $sunrise) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecuteSunriseSunsetAction(' . $this->InstanceID . ', ' . $SenderID . ', 0);';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                // Sunset
                $sunset = $this->ReadPropertyInteger('Sunset');
                if ($sunset != 0 && @IPS_ObjectExists($sunset)) {
                    if ($SenderID == $sunset) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecuteSunriseSunsetAction(' . $this->InstanceID . ', ' . $SenderID . ', 1);';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                // Is day
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecuteIsDayDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                // Twilight
                $id = $this->ReadPropertyInteger('TwilightStatus');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecuteTwilightDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                // Presence
                $id = $this->ReadPropertyInteger('PresenceStatus');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecutePresenceDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                // Triggers
                $triggers = json_decode($this->ReadPropertyString('Triggers'), true);
                if (!empty($triggers)) {
                    if (array_search($SenderID, array_column($triggers, 'ID')) !== false) {
                        $scriptText = 'KRS_CheckTrigger(' . $this->InstanceID . ', ' . $SenderID . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                // Emergency triggers
                $emergencyTriggers = json_decode($this->ReadPropertyString('EmergencyTriggers'), true);
                if (!empty($emergencyTriggers)) {
                    if (array_search($SenderID, array_column($emergencyTriggers, 'ID')) !== false) {
                        if ($Data[1]) {
                            $scriptText = 'KRS_ExecuteEmergencyTrigger(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                break;

            // $Data[0] = last run
            // $Data[1] = next run
            case EM_UPDATE:
                // Weekly schedule
                $scriptText = 'KRS_ExecuteWeeklyScheduleAction(' . $this->InstanceID . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    //#################### Private

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
                if ($messageType == EM_UPDATE) {
                    $this->UnregisterMessage($id, EM_UPDATE);
                }
            }
        }
    }

    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();
        // Actuator blind position
        if ($this->ReadPropertyBoolean('ActuatorUpdateBlindPosition')) {
            $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
            if ($id != 0 && IPS_ObjectExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
        // Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $sensor) {
                $use = $sensor->UseSettings;
                if ($use) {
                    $id = $sensor->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        // Sunrise
        $id = $this->ReadPropertyInteger('Sunrise');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Sunset
        $id = $this->ReadPropertyInteger('Sunset');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Weekly schedule
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, EM_UPDATE);
        }
        // Is day
        $id = $this->ReadPropertyInteger('IsDay');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Twilight status
        $id = $this->ReadPropertyInteger('TwilightStatus');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Presence status
        $id = $this->ReadPropertyInteger('PresenceStatus');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Triggers
        $triggers = json_decode($this->ReadPropertyString('Triggers'));
        if (!empty($triggers)) {
            foreach ($triggers as $variable) {
                if ($variable->UseSettings) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
        // Emergency triggers
        $triggers = json_decode($this->ReadPropertyString('EmergencyTriggers'));
        if (!empty($triggers)) {
            foreach ($triggers as $variable) {
                if ($variable->UseSettings) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
    }
}