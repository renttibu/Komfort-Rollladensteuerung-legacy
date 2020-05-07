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
        $variableName = 'Sunrise';
        $actionName = 'SunriseAction';
        if ($Mode == 1) {
            $modeName = 'Sonnenuntergang';
            $variableName = 'Sunset';
            $actionName = 'SunsetAction';
        }
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' (' . $modeName . ') hat sich geändert!', 0);
        $action = $this->CheckAction($variableName, $actionName);
        if (!$action) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $modeName . ' hat keine aktivierten Aktionen!', 0);
            return;
        }
        $settings = json_decode($this->ReadPropertyString($actionName), true);
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                if ($setting['UseSettings']) {
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