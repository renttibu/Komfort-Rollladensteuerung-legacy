<?php

// Declare
declare(strict_types=1);

trait KRS_doorWindowSensors
{
    //#################### Private

    /**
     * Checks the status of the activated door and window sensors.
     *
     * false    = all doors and windows are closed
     * true     = at least one door or window is opened
     */
    private function CheckDoorWindowSensors(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $status = false;
        $doorWindowStatus = boolval($this->GetValue('DoorWindowStatus'));
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $sensor) {
                if ($sensor->UseSettings) {
                    $id = $sensor->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = boolval(GetValue($id));
                        $triggerValue = boolval($sensor->TriggerValue);
                        if ($actualValue == $triggerValue) {
                            $status = true;
                        }
                    }
                }
            }
        }
        $this->SetValue('DoorWindowStatus', boolval($status));
        if ($doorWindowStatus != $status) {
            $this->SendDebug(__FUNCTION__, 'Der Tür- / Fensterstatus hat sich auf "' . GetValueFormatted($this->GetIDForIdent('DoorWindowStatus')) . '" geändert!', 0);
            // Closed
            $settings = json_decode($this->ReadPropertyString('DoorWindowCloseAction'), true)[0];
            if ($status) {
                $settings = json_decode($this->ReadPropertyString('DoorWindowOpenAction'), true)[0];
            }
            $selectPosition = $settings['SelectPosition'];
            switch ($selectPosition) {
                // None
                case 0:
                    $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                    return;
                    break;

                // Defined position
                case 1:
                    $position = $settings['DefinedPosition'];
                    break;

                // Last position
                case 2:
                    $position = intval($this->GetValue('LastPosition'));
                    break;

                // Setpoint position
                case 3:
                    $position = intval($this->GetValue('SetpointPosition'));
                    break;
            }
            if (isset($position)) {
                // Check conditions
                $conditions = [
                    ['type' => 0, 'condition' => ['Position' => $position, 'CheckPositionDifference' => $settings['CheckPositionDifference']]],
                    ['type' => 2, 'condition' => $settings['CheckAutomaticMode']],
                    ['type' => 3, 'condition' => $settings['CheckSleepMode']]];
                $checkConditions = $this->CheckConditions(json_encode($conditions));
                if (!$checkConditions) {
                    return;
                }
                // Trigger action
                if ($settings['UpdateSetpointPosition']) {
                    $this->SetValue('SetpointPosition', $position);
                }
                $this->MoveBlind($position, 0, 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Fensterstatus hat sich nicht geändert!', 0);
        }
    }
}