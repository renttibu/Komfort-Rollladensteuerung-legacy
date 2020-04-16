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
        $key = array_search($VariableID, array_column($settings, 'ID'));
        if (is_int($key)) {
            if (!$settings[$key]['UseSettings']) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ist deaktiviert!', 0);
                return;
            }
            $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' wurde aktualisiert.', 0);
            $actualValue = boolval(GetValue($VariableID));
            $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . json_encode($actualValue), 0);
            $triggerValue = boolval($settings[$key]['TriggerValue']);
            $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . json_encode($triggerValue), 0);
            // We have a trigger value
            if ($actualValue == $triggerValue) {
                $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' hat ausgelöst.', 0);
                // Check conditions
                $conditions = [
                    ['type' => 0, 'condition' => ['Position' => $settings[$key]['Position'], 'CheckPositionDifference' => $settings[$key]['CheckPositionDifference']]],
                    ['type' => 1, 'condition' => ['Position' => $settings[$key]['Position'], 'CheckLockoutProtection' => $settings[$key]['CheckLockoutProtection']]],
                    ['type' => 2, 'condition' => $settings[$key]['CheckAutomaticMode']],
                    ['type' => 3, 'condition' => $settings[$key]['CheckSleepMode']],
                    ['type' => 4, 'condition' => $settings[$key]['CheckBlindMode']],
                    ['type' => 5, 'condition' => $settings[$key]['CheckIsDay']],
                    ['type' => 6, 'condition' => $settings[$key]['CheckTwilight']],
                    ['type' => 7, 'condition' => $settings[$key]['CheckPresence']]];
                $checkConditions = $this->CheckConditions(json_encode($conditions));
                if (!$checkConditions) {
                    return;
                }
                // Trigger action
                $position = $settings[$key]['Position'];
                if ($settings[$key]['UpdateSetpointPosition']) {
                    $this->SetValue('SetpointPosition', $position);
                }
                $this->TriggerExecutionDelay(intval($settings[$key]['ExecutionDelay']));
                $this->MoveBlind($position, 0, 0);
            } else {
                $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' hat nicht ausgelöst.', 0);
            }
        }
    }
}