<?php

// Declare
declare(strict_types=1);

trait KRS_weeklySchedule
{
    /**
     * Shows the actual action of the weekly schedule.
     */
    public function ShowActualWeeklyScheduleAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $warning = json_decode('"\u26a0\ufe0f"') . " Fehler\n\n"; // warning
        $okay = json_decode('"\u2705"') . " Aktuelle Aktion\n\n"; // white_check_mark
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            echo $warning . 'Ein Wochenplan ist nicht vorhanden!';
            return;
        }
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $event = IPS_GetEvent($id);
            if ($event['EventActive'] != 1) {
                echo $warning . 'Der Wochenplan ist inaktiv!';
                return;
            } else {
                $actionID = $this->DetermineAction();
                $actionName = $warning . '0 = keine Aktion gefunden!';
                $event = IPS_GetEvent($id);
                foreach ($event['ScheduleActions'] as $action) {
                    if ($action['ID'] === $actionID) {
                        $actionName = $okay . $actionID . ' = ' . $action['Name'];
                    }
                }
                echo $actionName;
            }
        }
    }

    //#################### Private

    /**
     * Triggers the action of the weekly schedule.
     */
    public function ExecuteWeeklyScheduleAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Der Wochenplan hat ausgelöst.', 0);
        // Check event plan
        if (!$this->ValidateWeeklySchedule()) {
            return;
        }
        $actionID = $this->DetermineAction();
        $variableName = 'WeeklySchedule';
        switch ($actionID) {
            // Close
            case 1:
                $actionName = 'WeeklyScheduleActionOne';
                $action = $this->CheckAction($variableName, $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 1 = Schließen hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 1 = Schließen', 0);
                break;

            // Open
            case 2:
                $actionName = 'WeeklyScheduleActionTwo';
                $action = $this->CheckAction($variableName, $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 2 = Öffnen hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 2 = Öffnen', 0);
                break;

            // Shading
            case 3:
                $actionName = 'WeeklyScheduleActionThree';
                $action = $this->CheckAction($variableName, $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 3 = Beschatten hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 3 = Beschatten', 0);
                break;
        }
        if (isset($actionName)) {
            $settings = json_decode($this->ReadPropertyString($actionName), true);
            if (!empty($settings)) {
                foreach ($settings as $setting) {
                    if ($setting['UseSettings']) {
                        // Check conditions
                        $conditions = [
                            ['type' => 0, 'condition' => ['Position' => $setting['Position'], 'CheckPositionDifference' => $setting['CheckPositionDifference']]],
                            ['type' => 1, 'condition' => ['Position' => $setting['Position'], 'CheckLockoutProtection' => $setting['CheckLockoutProtection']]],
                            ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
                            ['type' => 3, 'condition' => $setting['CheckSleepMode']],
                            ['type' => 4, 'condition' => $setting['CheckBlindMode']],
                            ['type' => 5, 'condition' => $setting['CheckIsDay']],
                            ['type' => 6, 'condition' => $setting['CheckTwilight']],
                            ['type' => 7, 'condition' => $setting['CheckPresence']],
                            ['type' => 8, 'condition' => $setting['CheckDoorWindowStatus']]];
                        $checkConditions = $this->CheckConditions(json_encode($conditions));
                        if (!$checkConditions) {
                            continue;
                        }
                        // Trigger action
                        $position = $setting['Position'];
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

    /**
     * Determines the action from the weekly schedule.
     *
     * @return int
     * Returns the action id:
     * 1    = off
     * 2    = timer
     * 3    = on
     */
    private function DetermineAction(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $actionID = 0;
        if ($this->ValidateWeeklySchedule()) {
            $timestamp = time();
            $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
            $weekDay = date('N', $timestamp);
            $id = $this->ReadPropertyInteger('WeeklySchedule');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $event = IPS_GetEvent($id);
                foreach ($event['ScheduleGroups'] as $group) {
                    if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                        $points = $group['Points'];
                        foreach ($points as $point) {
                            $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                            if ($startTime <= $searchTime) {
                                $actionID = $point['ActionID'];
                            }
                        }
                    }
                }
            }
        }
        return $actionID;
    }

    /**
     * Validates the weekly schedule.
     *
     * @return bool
     * false    = failed
     * true     = valid
     */
    private function ValidateWeeklySchedule(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = false;
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $event = IPS_GetEvent($id);
            if ($event['EventActive'] == 1) {
                $result = true;
            }
        }
        if (!$result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wochenplan ist nicht vorhanden oder deaktiviert!', 0);
        }
        return $result;
    }
}