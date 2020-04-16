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
        $id = $this->ReadPropertyInteger('IsDay');
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $id . ' (Ist es Tag) hat sich geändert!', 0);
        $actualStatus = boolval(GetValue($id)); // false = night, true = day
        $settings = json_decode($this->ReadPropertyString('NightAction'), true)[0];
        $statusName = 'Es ist Nacht';
        if ($actualStatus) {
            $settings = json_decode($this->ReadPropertyString('DayAction'), true)[0];
            $statusName = 'Es ist Tag';
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Status: ' . $statusName, 0);
        if (!$settings['UseSettings']) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, ' . $statusName . ' ist deaktiviert!', 0);
            return;
        }
        // Check conditions
        $conditions = [
            ['type' => 0, 'condition' => ['Position' => $settings['Position'], 'CheckPositionDifference' => $settings['CheckPositionDifference']]],
            ['type' => 1, 'condition' => ['Position' => $settings['Position'], 'CheckLockoutProtection' => $settings['CheckLockoutProtection']]],
            ['type' => 2, 'condition' => $settings['CheckAutomaticMode']],
            ['type' => 3, 'condition' => $settings['CheckSleepMode']],
            ['type' => 4, 'condition' => $settings['CheckBlindMode']],
            ['type' => 6, 'condition' => $settings['CheckTwilight']],
            ['type' => 7, 'condition' => $settings['CheckPresence']]];
        $checkConditions = $this->CheckConditions(json_encode($conditions));
        if (!$checkConditions) {
            return;
        }
        // Trigger action
        $position = $settings['Position'];
        if ($settings['UpdateSetpointPosition']) {
            $this->SetValue('SetpointPosition', $position);
        }
        $this->TriggerExecutionDelay(intval($settings['ExecutionDelay']));
        $this->MoveBlind($position, 0, 0);
    }
}