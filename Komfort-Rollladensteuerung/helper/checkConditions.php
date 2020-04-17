<?php

// Declare
declare(strict_types=1);

trait KRS_checkConditions
{
    /**
     * Checks the conditions.
     *
     * @param string $Conditions
     * 0    = position difference
     * 1    = lockout protection
     * 2    = automatic mode
     * 3    = sleep mode
     * 4    = blind mode
     * 5    = is day
     * 6    = twilight
     * 7    = presence
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckConditions(string $Conditions): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $Conditions = json_decode($Conditions, true);
        if (!empty($Conditions)) {
            $results = [];
            foreach ($Conditions as $condition) {
                switch ($condition['type']) {
                    // Position difference
                    case 0:
                        $checkPositionDifference = $this->CheckPositionDifferenceCondition($condition['condition']);
                        $results[$condition['type']] = $checkPositionDifference;
                        break;

                    // Lockout protection
                    case 1:
                        $checkLockoutProtection = $this->CheckLockoutProtectionCondition($condition['condition']);
                        $results[$condition['type']] = $checkLockoutProtection;
                        break;

                    // Automatic mode
                    case 2:
                        $checkAutomaticMode = $this->CheckAutomaticModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkAutomaticMode;
                        break;

                    // Sleep mode
                    case 3:
                        $checkSleepMode = $this->CheckSleepModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkSleepMode;
                        break;

                    // Blind mode
                    case 4:
                        $checkBlindMode = $this->CheckBlindModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkBlindMode;
                        break;

                    // Is day
                    case 5:
                        $checkIsDay = $this->CheckIsDayCondition($condition['condition']);
                        $results[$condition['type']] = $checkIsDay;
                        break;

                    // Twilight
                    case 6:
                        $checkTwilight = $this->CheckTwilightCondition($condition['condition']);
                        $results[$condition['type']] = $checkTwilight;
                        break;

                    // Presence
                    case 7:
                        $checkPresence = $this->CheckPresenceCondition($condition['condition']);
                        $results[$condition['type']] = $checkPresence;
                        break;

                }
            }
            if (in_array(false, $results)) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Bedingungen wurden nicht erfüllt!', 0);
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Checks the position difference.
     *
     * @param array $Conditions
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckPositionDifferenceCondition(array $Conditions): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $checkPositionDifference = $Conditions['CheckPositionDifference'];
        $newBlindPosition = $Conditions['Position'];
        if ($checkPositionDifference > 0) { // 0 = don't check for position difference, > 0 check position difference
            $actualDifference = abs($this->GetActualBlindPosition() - $newBlindPosition);
            $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $actualDifference . '%.', 0);
            if ($actualDifference <= $checkPositionDifference) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, der Positionsunterschied ist zu gering!', 0);
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Checks the lockout protection.
     *
     * @param array $Conditions
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckLockoutProtectionCondition(array $Conditions): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $checkLockoutPosition = $Conditions['CheckLockoutProtection'];
        // Check moving direction
        $direction = $this->CheckBlindMovingDirection(intval($Conditions['Position']));
        $doorWindowStatus = boolval($this->GetValue('DoorWindowStatus'));
        if ($checkLockoutPosition == 100) { // always, don't move blind down
            if ($direction == 0 && $doorWindowStatus) { // down
                $this->SendDebug(__FUNCTION__, 'Abbruch, der Aussperrschutz ist aktiv!', 0);
                $result = false;
            }
        }
        if ($checkLockoutPosition > 0 && $checkLockoutPosition < 100) { // check position
            if ($direction == 0 && $doorWindowStatus) { // down
                $actualBlindPosition = $this->GetActualBlindPosition();
                $newBlindPosition = $Conditions['Position'];
                if ($newBlindPosition <= $actualBlindPosition) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Aussperrschutz ist bei ' . $checkLockoutPosition . '% aktiv!', 0);
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * Checks the automatic mode condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = automatic mode must be off
     * 2    = automatic mode must be on
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckAutomaticModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $automaticMode = boolval($this->GetValue('AutomaticMode')); // false = automatic mode is off, true = automatic mode is on
        switch ($Condition) {
            // Automatic mode must be off
            case 1:
                if ($automaticMode) { // Automatic mode is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Aus', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Automatik ist eingeschaltet!', 0);
                    $result = false;
                }
                break;

            // Automatic mode must be on
            case 2:
                if (!$automaticMode) { // Automatic mode is off
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = An', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Automatik ist ausgeschaltet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    /**
     * Checks the sleep mode condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = automatic mode must be off
     * 2    = automatic mode must be on
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckSleepModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $sleepMode = boolval($this->GetValue('SleepMode')); // false = sleep mode is off, true = sleep mode is on
        switch ($Condition) {
            // Sleep mode must be off
            case 1:
                if ($sleepMode) { // Sleep mode is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Aus', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Ruhe-Modus ist eingeschaltet!', 0);
                    $result = false;
                }
                break;

            // Sleep mode must be on
            case 2:
                if (!$sleepMode) { // Sleep mode is off
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = An', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Ruhe-Modus ist ausgeschaltet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    /**
     * Checks the blind mode condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = blind must be closed
     * 2    = timer must be on
     * 3    = timer must be on or blind must be opened
     * 4    = blind must be opened
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     *
     */
    private function CheckBlindModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $blindMode = intval($this->GetValue('BlindMode')); // 0 = closed, 1 = stop, 2 = timer, 3 = opened
        switch ($Condition) {
            // Blind must be closed
            case 1:
                if ($blindMode == 2 || $blindMode == 3) { // Timer is on or blind is opened
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Geschlossen', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rolladen ist nicht geschlossen!', 0);
                    $result = false;
                }
                break;

            // Timer must be on
            case 2:
                if ($blindMode == 0 || $blindMode == 3) { // Blind is closed or opened
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Timer', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Timer ist nicht aktiv!', 0);
                    $result = false;
                }
                break;

            // Timer must be on or blind must be opened
            case 3:
                if ($blindMode == 0) { // Blind is closed
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 3 = Timer - Geöffnet', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rollladen ist geschlossen!', 0);
                    $result = false;
                }
                break;

            // Blind must be opened
            case 4:
                if ($blindMode == 0 || $blindMode == 2) { // Blind is closed or timer is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 4 =  Geöffnet', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rolladen ist nicht geöffnet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    /**
     * Checks the is day condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = must be night
     * 2    = must be day
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckIsDayCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        switch ($Condition) {
            // Must be night
            case 1:
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Ist es Tag - Prüfung ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $isDayStatus = boolval(GetValue($id));
                    if ($isDayStatus) { // Day
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Es ist Nacht', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Tag!', 0);
                        $result = false;
                    }
                }
                break;

            // Must be day
            case 2:
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Ist es Tag - Prüfung ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $isDayStatus = boolval(GetValue($id));
                    if (!$isDayStatus) { // Night
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Es ist Tag', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Nacht!', 0);
                        $result = false;
                    }
                }
                break;
        }
        return $result;
    }

    /**
     * Checks the twilight condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = must be day
     * 2    = must be night
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckTwilightCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $id = $this->ReadPropertyInteger('TwilightStatus');
        switch ($Condition) {
            // Must be day
            case 1:
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Dämmerungsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $twilightStatus = boolval(GetValue($id));
                    if ($twilightStatus) { // Night
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Es ist Tag', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Nacht!', 0);
                        $result = false;
                    }
                }
                break;

            // Must be night
            case 2:
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Dämmerungsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $twilightStatus = boolval(GetValue($id));
                    if (!$twilightStatus) { // Day
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Es ist Nacht', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Tag!', 0);
                        $result = false;
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * Checks the presence condition.
     *
     * @param int $Condition
     * 0    = none
     * 1    = status must be absence
     * 2    = status must be presence
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckPresenceCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $id = $this->ReadPropertyInteger('PresenceStatus');
        switch ($Condition) {
            // Must be absence
            case 1:
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Anwesenheitsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $presenceStatus = boolval(GetValue($id));
                    if ($presenceStatus) { // Presence
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Abwesenheit', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Anwesenheit!', 0);
                        $result = false;
                    }
                }
                break;
            // Must be presence
            case 2:
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Anwesenheitsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $presenceStatus = boolval(GetValue($id));
                    if (!$presenceStatus) { // Absence
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Anwesenheit', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Abwesenheit!', 0);
                        $result = false;
                    }
                }
                break;

        }
        return $result;
    }
}