<?php

// Declare
declare(strict_types=1);

trait KRS_sunriseSunset
{
    /**
     * Execute the sunrise or sunset action.
     *
     * @param int $VariableID
     * @param int $Mode
     * 0    = sunrise
     * 1    = sunset
     */
    public function ExecuteSunriseSunsetAction(int $VariableID, int $Mode): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $modeName = 'Sonnenaufgang';
        $settings = json_decode($this->ReadPropertyString('Sunrise'), true)[0];
        if ($Mode == 1) {
            $modeName = 'Sonnenuntergang';
            $settings = json_decode($this->ReadPropertyString('Sunset'), true)[0];
        }
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' (' . $modeName . ') hat sich geändert!', 0);
        if (!$settings['UseSettings']) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $modeName . ' ist deaktiviert!', 0);
            return;
        }
        $conditions = [
            ['type' => 0, 'condition' => ['Position' => $settings['Position'], 'CheckPositionDifference' => $settings['CheckPositionDifference']]],
            ['type' => 1, 'condition' => ['Position' => $settings['Position'], 'CheckLockoutProtection' => $settings['CheckLockoutProtection']]],
            ['type' => 2, 'condition' => $settings['CheckAutomaticMode']],
            ['type' => 3, 'condition' => $settings['CheckSleepMode']],
            ['type' => 4, 'condition' => $settings['CheckBlindMode']],
            ['type' => 5, 'condition' => $settings['CheckIsDay']],
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