<?php

// Declare
declare(strict_types=1);

trait KRS_isDayDetection
{
    /**
     * Executes the is day detection.
     */
    public function ExecuteIsDayDetection(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('IsDay');
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $id . ' (Ist es Tag) hat sich geändert!', 0);
        $actualStatus = boolval(GetValue($id)); // false = night, true = day
        $statusName = 'Es ist Nacht';
        $actionName = 'NightAction';
        if ($actualStatus) {
            $statusName = 'Es ist Tag';
            $actionName = 'DayAction';
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Status: ' . $statusName, 0);
        $action = $this->CheckAction('IsDay', $actionName);
        if (!$action) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $statusName . ' hat keine aktivierten Aktionen!', 0);
            return;
        }
        $settings = json_decode($this->ReadPropertyString($actionName), true);
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                if ($setting['UseSettings']) {
                    $selectPosition = $setting['SelectPosition'];
                    switch ($selectPosition) {
                        // None
                        case 0:
                            $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                            continue 2;

                        // Defined position
                        case 1:
                            $position = $setting['Position'];
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
                            ['type' => 0, 'condition' => ['Position' => $position, 'CheckPositionDifference' => $setting['CheckPositionDifference']]],
                            ['type' => 1, 'condition' => ['Position' => $position, 'CheckLockoutProtection' => $setting['CheckLockoutProtection']]],
                            ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
                            ['type' => 3, 'condition' => $setting['CheckSleepMode']],
                            ['type' => 4, 'condition' => $setting['CheckBlindMode']],
                            ['type' => 6, 'condition' => $setting['CheckTwilight']],
                            ['type' => 7, 'condition' => $setting['CheckPresence']],
                            ['type' => 8, 'condition' => $setting['CheckDoorWindowStatus']]];
                        $checkConditions = $this->CheckConditions(json_encode($conditions));
                        if (!$checkConditions) {
                            continue;
                        }
                        // Trigger action
                        //$position = $setting['Position'];
                        if ($setting['UpdateSetpointPosition']) {
                            $this->SetValue('SetpointPosition', $position);
                        }
                        if ($setting['UpdateLastPosition']) {
                            $this->SetValue('LastPosition', $position);
                        }
                        $this->TriggerExecutionDelay(intval($setting['ExecutionDelay']));
                        $this->MoveBlind($position, 0, 0);
                    }
                }
            }
        }
    }
}