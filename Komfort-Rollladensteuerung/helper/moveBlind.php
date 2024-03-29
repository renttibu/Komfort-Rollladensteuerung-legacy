<?php

// Declare
declare(strict_types=1);

trait KRS_moveBlind
{
    /**
     * Moves the blind to the position.
     *
     * @param int $Position
     * @param int $Duration
     * @param int $DurationUnit
     * 0    = seconds
     * 1    = minutes
     *
     * @return bool
     */
    public function MoveBlind(int $Position, int $Duration = 0, int $DurationUnit = 0): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = false;
        $id = $this->ReadPropertyInteger('ActuatorControl');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Rollladenaktor vorhanden!', 0);
            return $result;
        }
        // Check activity status
        $activityStatus = $this->ReadPropertyInteger('ActuatorActivityStatus');
        if ($activityStatus != 0 && @IPS_ObjectExists($activityStatus)) {
            if (intval(GetValue($activityStatus)) == 1) {
                $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt ist noch nicht abgeschlossen!', 0);
                $useStopFunction = $this->ReadPropertyBoolean('EnableStopFunction');
                if ($useStopFunction) {
                    $this->StopBlindMoving();
                    return $result;
                }
            }
        }
        $actualBlindMode = intval($this->GetValue('BlindMode'));
        $actualBlindSliderValue = floatval($this->GetValue('BlindSlider'));
        //$actualPositionPreset = intval($this->GetValue('PositionPresets'));
        $actualLastPosition = intval($this->GetValue('LastPosition'));
        // Closed
        if ($Position == 0) {
            $mode = 0;
            $modeText = 'geschlossen';
            $this->DeactivateBlindModeTimer();
        }
        // Timer
        if ($Position > 0 && $Duration != 0) {
            $mode = 2;
            $modeText = 'bewegt (Timer)';
            $this->SetBlindTimer($Duration, $DurationUnit);
        }
        // On
        if ($Position > 0 && $Duration == 0) {
            $mode = 3;
            $modeText = 'geöffnet';
            if ($actualBlindMode == 2) {
                $this->DeactivateBlindModeTimer();
            }
        }
        if (isset($modeText)) {
            $this->SendDebug(__FUNCTION__, 'Der Rollladen wird auf ' . $Position . '% ' . $modeText . '.', 0);
        }
        if (isset($mode)) {
            $this->SetValue('BlindMode', $mode);
            $this->SetValue('BlindSlider', $Position);
            //$this->SetClosestPositionPreset($Position);
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                // Boolean
                case 0:
                    $actualVariableValue = boolval(GetValue($id));
                    $newVariableValue = boolval($Position);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = !$newVariableValue;
                    }
                    break;

                // Integer
                case 1:
                    $actualVariableValue = intval(GetValue($id));
                    $newVariableValue = intval($Position);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = abs($newVariableValue - 100);
                    }
                    break;

                // Float
                case 2:
                    $actualVariableValue = floatval(GetValue($id));
                    $newVariableValue = floatval($Position / 100);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = abs($newVariableValue - 1);
                    }
                    break;
            }
            if (isset($actualVariableValue) && isset($newVariableValue)) {
                if ($actualVariableValue == $newVariableValue) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $id . ' hat bereits den Wert: ' . json_encode($newVariableValue) . ' = ' . $Position . '%!', 0);
                    return false;
                } else {
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ', neuer Wert: ' . $newVariableValue . ', Position: ' . json_encode($Position) . '%', 0);
                    $result = @RequestAction($id, $newVariableValue);
                    if (!$result) {
                        IPS_Sleep(self::DEVICE_DELAY_MILLISECONDS);
                        $result = @RequestAction($id, $newVariableValue);
                        if (!$result) {
                            if (isset($modeText)) {
                                $this->SendDebug(__FUNCTION__, 'Fehler, der Rolladen mit der ID ' . $id . ' konnte nicht ' . $modeText . ' werden!', 0);
                                IPS_LogMessage(__FUNCTION__, 'Fehler, der Rolladen mit der ID ' . $id . ' konnte nicht ' . $modeText . ' werden!');
                            }
                        }
                    }
                    if (!$result) {
                        // Revert switch
                        $this->SetValue('BlindMode', $actualBlindMode);
                        $this->SetValue('BlindSlider', $actualBlindSliderValue);
                        //$this->SetValue('PositionPresets', $actualPositionPreset);
                        $this->SetValue('LastPosition', $actualLastPosition);
                    } else {
                        if (isset($modeText)) {
                            $this->SendDebug(__FUNCTION__, 'Der Rollladen wurde ' . $modeText . '.', 0);
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function StopBlindTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->DeactivateBlindModeTimer();
        $settings = json_decode($this->ReadPropertyString('Timer'), true)[0];
        $operationalAction = intval($settings['OperationalAction']);
        switch ($operationalAction) {
            // None
            case 0:
                $this->SendDebug(__FUNCTION__, 'Aktion: Keine', 0);
                break;

            // Last position
            case 1:
                $lastPosition = intval($this->GetValue('LastPosition'));
                $this->SendDebug(__FUNCTION__, 'Aktion: Letzte Position, ' . $lastPosition . '%', 0);
                $this->MoveBlind($lastPosition, 0, 0);
                break;

            // Setpoint position
            case 2:
                $setpointPosition = intval($this->GetValue('SetpointPosition'));
                $this->SendDebug(__FUNCTION__, 'Aktion: Soll-Position, ' . $setpointPosition . '%', 0);
                $this->MoveBlind($setpointPosition, 0, 0);
                break;

            // Defined position
            case 3:
                $definedPosition = intval($settings['DefinedPosition']);
                $this->SendDebug(__FUNCTION__, 'Aktion: Definerte Position, ' . $definedPosition . '%', 0);
                $this->MoveBlind($definedPosition, 0, 0);
                break;
        }
    }

    //##################### Private

    /**
     * Updates the blind position.
     */
    private function UpdateBlindPosition(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorActivityStatus');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $updateBlindPosition = $this->ReadPropertyBoolean('ActuatorUpdateBlindPosition');
            if (!$updateBlindPosition) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Aktualisierung der Rollladenposition ist deaktiviert!', 0);
            }
            if (GetValue($id) == 0) {
                $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $actualPosition = intval($this->GetActualBlindPosition());
                    $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualPosition . '%.', 0);
                    $blindMode = 0;
                    if ($actualPosition > 0) {
                        $blindMode = 3;
                    }
                    $this->SetValue('BlindMode', intval($blindMode));
                    $this->SetValue('BlindSlider', intval($actualPosition));
                    //$this->SetClosestPositionPreset($actualPosition);
                    if ($this->ReadPropertyBoolean('ActuatorUpdateSetpointPosition')) {
                        $this->SetValue('SetpointPosition', $actualPosition);
                    }
                    if ($this->ReadPropertyBoolean('ActuatorUpdateLastPosition')) {
                        $this->SetValue('LastPosition', $actualPosition);
                    }
                }
            }
        }
    }

    /**
     * Stops the blind moving.
     */
    private function StopBlindMoving(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorControl');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Rollladenaktor vorhanden!', 0);
            return;
        }
        $parent = IPS_GetParent($id);
        if ($parent != 0 && @IPS_ObjectExists($parent)) {
            $moduleID = IPS_GetInstance($parent)['ModuleInfo']['ModuleID'];
            if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, der zugewiesene Rollladenaktor ist kein Homematic Gerät!', 0);
                return;
            }
            $result = HM_WriteValueBoolean($parent, 'STOP', true);
            if (!$result) {
                $this->SendDebug(__FUNCTION__, 'Fehler, die Rollladenfahrt konnte nicht gestoppt werden!', 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Fehler, die Rollladenfahrt konnte nicht gestoppt werden!', KL_ERROR);
            } else {
                $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt wurde gestoppt.', 0);
            }
        }
    }

    /**
     * Checks the moving direction of the blind.
     *
     * @param int $Position
     * @return int
     * 0    = down
     * 1    = up
     */
    private function CheckBlindMovingDirection(int $Position): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = 0; // down
        $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                // Boolean
                case 0:
                    $actualBlindPosition = boolval(GetValue($id) * 100);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = !$actualBlindPosition;
                    }
                    break;

                // Integer
                case 1:
                    $actualBlindPosition = intval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = abs($actualBlindPosition - 100);
                    }
                    break;

                // Float
                case 2:
                    $actualLevel = floatval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = abs($actualLevel - 1);
                    }
                    $actualBlindPosition = $actualLevel * 100;
                    break;
            }
            if (isset($actualBlindPosition)) {
                if ($Position > $actualBlindPosition) {
                    $result = 1; // up
                }
            }
        }
        if ($result == 0) {
            $movingText = 'heruntergefahren';
        } else {
            $movingText = 'hochgefahren';
        }
        $this->SendDebug(__FUNCTION__, 'Der Rollladen soll ' . $movingText . ' werden.', 0);
        return $result;
    }

    /**
     * Gets the actual blind position.
     *
     * @return int
     */
    private function GetActualBlindPosition(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $actualBlindPosition = 0;
        $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                // Boolean
                case 0:
                    $actualLevel = boolval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = !$actualLevel;
                    }
                    $actualBlindPosition = intval($actualLevel * 100);
                    break;

                // Integer
                case 1:
                    $actualBlindPosition = intval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = abs($actualBlindPosition - 100);
                    }
                    break;

                // Float
                case 2:
                    $actualLevel = floatval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = abs($actualLevel - 1);
                    }
                    $actualBlindPosition = intval($actualLevel * 100);
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Aktuelle Rollladenposition: ' . $actualBlindPosition . '%', 0);
        return $actualBlindPosition;
    }

    /**
     * Registers the blind timer.
     */
    private function RegisterBlindTimer(): void
    {
        $this->RegisterTimer('StopBlindTimer', 0, 'KRS_StopBlindTimer(' . $this->InstanceID . ');');
    }

    /**
     * Sets the interval for the blind timer.
     *
     * @param int $Duration
     * @param int $DurationUnit
     * 0    = seconds
     * 1    = minutes
     */
    private function SetBlindTimer(int $Duration, int $DurationUnit): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($DurationUnit == 1) {
            $Duration = $Duration * 60;
        }
        $this->SetTimerInterval('StopBlindTimer', $Duration * 1000);
        $timestamp = time() + $Duration;
        $this->SetValue('BlindModeTimer', $this->GetTimeStampString($timestamp));
        $this->SendDebug(__FUNCTION__, 'Die Dauer des Timers wurde festgelegt.', 0);
    }

    /**
     * Deactivates the blind timer.
     */
    private function DeactivateBlindModeTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('StopBlindTimer', 0);
        $this->SetValue('BlindModeTimer', '-');
        $this->SendDebug(__FUNCTION__, 'Der Timer wurde deaktiviert.', 0);
    }

    /**
     * Triggers an execution delay.
     *
     * @param int $Delay
     */
    private function TriggerExecutionDelay(int $Delay): void
    {
        if ($Delay != 0) {
            $this->SendDebug(__FUNCTION__, 'Die Verzögerung von ' . $Delay . ' Sekunden wird ausgeführt.', 0);
            IPS_Sleep($Delay * 1000);
        }
    }
}