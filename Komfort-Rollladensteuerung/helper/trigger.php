<?php

// Declare
declare(strict_types=1);

trait KRS_trigger
{
    /**
     * Checks a trigger variable for action.
     *
     * @param int $VariableID
     */
    public function CheckTrigger(int $VariableID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $settings = json_decode($this->ReadPropertyString('Triggers'), true);
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                $id = $setting['ID'];
                if ($VariableID == $id) {
                    if ($setting['UseSettings']) {
                        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' wurde aktualisiert.', 0);
                        $actualValue = boolval(GetValue($VariableID));
                        $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . json_encode($actualValue), 0);
                        $triggerValue = boolval($setting['TriggerValue']);
                        $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . json_encode($triggerValue), 0);
                        // We have a trigger value
                        if ($actualValue == $triggerValue) {
                            $this->SendDebug(__FUNCTION__, 'Die Aktion für die Variable ' . $VariableID . ' wird verwendet.', 0);
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
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Die Aktion für die Variable ' . $VariableID . ' wird nicht verwendet.', 0);
                        }
                    }
                }
            }
        }
    }
}