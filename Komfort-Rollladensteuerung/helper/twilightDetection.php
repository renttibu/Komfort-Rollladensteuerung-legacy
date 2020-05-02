<?php

// Declare
declare(strict_types=1);

trait KRS_twilightDetection
{
    /**
     * Executes the is day detection.
     */
    public function ExecuteTwilightDetection(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('TwilightStatus');
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $id . ' (Dämmerungsstatus) hat sich geändert!', 0);
        $actualStatus = boolval(GetValue($id)); // false = day, true = night
        $statusName = 'Es ist Tag';
        $actionName = 'TwilightDayAction';
        if ($actualStatus) { // Night
            $statusName = 'Es ist Nacht';
            $actionName = 'TwilightNightAction';
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Status: ' . $statusName, 0);
        $action = $this->CheckAction('TwilightStatus', $actionName);
        if (!$action) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $statusName . ' hat keine aktivierten Aktionen!', 0);
            return;
        }
        $settings = json_decode($this->ReadPropertyString($actionName), true);
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                // Check conditions
                $conditions = [
                    ['type' => 0, 'condition' => ['Position' => $setting['Position'], 'CheckPositionDifference' => $setting['CheckPositionDifference']]],
                    ['type' => 1, 'condition' => ['Position' => $setting['Position'], 'CheckLockoutProtection' => $setting['CheckLockoutProtection']]],
                    ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
                    ['type' => 3, 'condition' => $setting['CheckSleepMode']],
                    ['type' => 4, 'condition' => $setting['CheckBlindMode']],
                    ['type' => 5, 'condition' => $setting['CheckIsDay']],
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