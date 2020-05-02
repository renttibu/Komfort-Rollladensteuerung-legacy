<?php

// Declare
declare(strict_types=1);

trait KRS_presenceDetection
{
    /**
     * Executes the presence detection.
     */
    public function ExecutePresenceDetection(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('PresenceStatus');
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $id . ' (Anwesenheitsstatus) hat sich geändert!', 0);
        $actualStatus = boolval(GetValue($id)); // false = absence, true = presence
        $statusName = 'Abwesenheit';
        $actionName = 'AbsenceAction';
        if ($actualStatus) { // Presence
            $statusName = 'Anwesenheit';
            $actionName = 'PresenceAction';
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Status: ' . $statusName, 0);
        $action = $this->CheckAction('PresenceStatus', $actionName);
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
                    ['type' => 6, 'condition' => $setting['CheckTwilight']],
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