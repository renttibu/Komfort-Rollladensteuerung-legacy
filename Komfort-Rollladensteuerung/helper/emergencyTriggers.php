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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $settings = json_decode($this->ReadPropertyString('EmergencyTriggers'), true);
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
                            $position = $setting['Position'];
                            $this->MoveBlind($position, 0, 0);
                            $automaticMode = intval($setting['AutomaticMode']);
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
                            $sleepMode = intval($setting['SleepMode']);
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
                            $this->SendDebug(__FUNCTION__, 'Die Aktion für die Variable ' . $VariableID . ' wird nicht verwendet.', 0);
                        }
                    }
                }
            }
        }
    }
}