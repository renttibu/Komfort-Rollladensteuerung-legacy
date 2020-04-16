<?php

// Declare
declare(strict_types=1);

trait KRS_emergencyTriggers
{
    //#################### Private

    /**
     * Executes the emergency sensor action.
     *
     * @param int $VariableID
     */
    public function ExecuteEmergencyTrigger(int $VariableID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $settings = json_decode($this->ReadPropertyString('EmergencyTriggers'), true);
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
                $position = $settings[$key]['Position'];
                $this->MoveBlind($position, 0, 0);
                $automaticMode = intval($settings[$key]['AutomaticMode']);
                switch ($automaticMode) {
                    // Off
                    case 1:
                        $this->ToggleAutomaticMode(false);
                        break;

                    // On
                    case 2:
                        $this->ToggleAutomaticMode(true);
                        break;

                }
                $sleepMode = intval($settings[$key]['SleepMode']);
                switch ($sleepMode) {
                    // Off
                    case 1:
                        $this->ToggleSleepMode(false);
                        break;

                    // On
                    case 2:
                        $this->ToggleSleepMode(true);
                        break;

                }
            } else {
                $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' hat nicht ausgelöst.', 0);
            }
        }
    }
}