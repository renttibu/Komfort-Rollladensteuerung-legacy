<?php

/*
 * @module      Komfort-Rollladensteuerung
 *
 * @prefix      KRS
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     2.00-4
 * @date        2020-04-16, 18:00, 1587056400
 * @review      2020-04-16, 18:00
 *
 * @see         https://github.com/ubittner/Komfort-Rollladensteuerung
 *
 * @guids       Library
 *              {ECB37AE6-38B5-88A5-DC79-D8E0DD1879B2}
 *
 *              Komfort-Rollladensteuerung
 *             	{A346C14F-D31A-2E75-F0CA-5A5F2709C125}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class KomfortRollladensteuerung extends IPSModule
{
    // Helper
    use KRS_actuator;
    use KRS_backupRestore;
    use KRS_checkConditions;
    use KRS_doorWindowSensors;
    use KRS_emergencyTriggers;
    use KRS_isDayDetection;
    use KRS_messageSink;
    use KRS_moveBlind;
    use KRS_presenceDetection;
    use KRS_sunriseSunset;
    use KRS_switchingTime;
    use KRS_trigger;
    use KRS_twilightDetection;
    use KRS_weeklySchedule;

    // Constants
    private const DEVICE_DELAY_MILLISECONDS = 250;
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        // Register properties
        $this->RegisterProperties();
        // Create profiles
        $this->CreateProfiles();
        // Register variables
        $this->RegisterVariables();
        // Sleep mode timer
        $this->RegisterSleepModeTimer();
        // Register blind timer
        $this->RegisterBlindTimer();
        // Register switching timers
        $this->RegisterSwitchingTimers();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Never delete this line!
        parent::ApplyChanges();
        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        // Register messages
        $this->RegisterMessages();
        // Update position presets
        $this->UpdatePositionPresets();
        // Create links
        $this->CreateLinks();
        // Set options
        $this->SetOptions();
        // Deactivate sleep mode
        $this->DeactivateSleepModeTimer();
        // Deactivate blind mode
        $this->DeactivateBlindModeTimer();
        // Set switching timers
        $this->SetSwitchingTimes();
        // Check door and windows
        $this->CheckDoorWindowSensors();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        // Delete profiles
        $this->DeleteProfiles();
    }

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'));
        // Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $variable) {
                $rowColor = '';
                $id = $variable->ID;
                if ($id == 0 || !IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; // light red
                }
                $formData->elements[3]->items[1]->values[] = ['rowColor' => $rowColor];
            }
        }
        // Trigger
        $triggerVariables = json_decode($this->ReadPropertyString('Triggers'));
        if (!empty($triggerVariables)) {
            foreach ($triggerVariables as $variable) {
                $rowColor = '';
                $id = $variable->ID;
                if ($id == 0 || !IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; // light red
                }
                $formData->elements[10]->items[1]->values[] = ['rowColor' => $rowColor];
            }
        }
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            if (!IPS_ObjectExists($senderID)) {
                foreach ($messageID as $messageType) {
                    $this->UnregisterMessage($senderID, $messageType);
                }
                continue;
            } else {
                $senderName = IPS_GetName($senderID);
                $description = $senderName;
                $parentID = IPS_GetParent($senderID);
                if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                    $description = IPS_GetName($parentID);
                }
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData->actions[1]->items[0]->values[] = [
                'Description'        => $description,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formData);
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;

            case 'SleepMode':
                $this->ToggleSleepMode($Value);
                break;

            case 'BlindMode':
                $this->ExecuteBlindMode($Value);
                break;

            case 'BlindSlider':
                $this->SetBlindSlider($Value);
                break;

            case 'PositionPresets':
                $this->ExecutePositionPreset($Value);
                break;

        }
    }

    /**
     * Toggles the automatic mode.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAutomaticMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetValue('AutomaticMode', $State);
    }

    /**
     * Toggles the sleep mode.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleSleepMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetValue('SleepMode', $State);
        if ($State) {
            $this->SetSleepModeTimer();
        } else {
            $this->DeactivateSleepModeTimer();
        }
    }

    /**
     * Executes the blind mode.
     *
     * @param int $Mode
     * 0    = close blind
     * 1    = timer
     * 2    = open blind
     */
    public function ExecuteBlindMode(int $Mode): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        switch ($Mode) {
            // Close
            case 0:
                $settings = json_decode($this->ReadPropertyString('CloseBlind'), true)[0];
                $position = intval($settings['Position']);
                if (boolval($settings['UpdateSetpointPosition'])) {
                    $this->SetValue('SetpointPosition', $position);
                }
                $this->MoveBlind($position, 0, 0);
                break;

            // Timer
            case 1:
                $settings = json_decode($this->ReadPropertyString('Timer'), true)[0];
                $position = intval($settings['Position']);
                if (boolval($settings['UpdateSetpointPosition'])) {
                    $this->SetValue('SetpointPosition', $position);
                }
                $duration = $settings['Duration'];
                $durationUnit = $settings['DurationUnit'];
                $this->MoveBlind($position, $duration, $durationUnit);
                break;

            // Open
            case 2:
                $settings = json_decode($this->ReadPropertyString('OpenBlind'), true)[0];
                $position = intval($settings['Position']);
                if (boolval($settings['UpdateSetpointPosition'])) {
                    $this->SetValue('SetpointPosition', $position);
                }
                $this->MoveBlind($position, 0, 0);
                break;

        }
    }

    /**
     * Sets the blind slider and moves the blind to the position.
     *
     * @param int $Position
     */
    public function SetBlindSlider(int $Position): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->ReadPropertyBoolean('BlindSliderUpdateSetpointPosition')) {
            $this->SetValue('SetpointPosition', $Position);
        }
        $this->MoveBlind(intval($Position), 0, 0);
    }

    /**
     * Executes a preset and moves the blind to the position.
     *
     * @param int $Position
     */
    public function ExecutePositionPreset(int $Position): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->ReadPropertyBoolean('PositionPresetsUpdateSetpointPosition')) {
            $this->SetValue('SetpointPosition', $Position);
        }
        $this->MoveBlind(intval($Position), 0, 0);
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // General options
        $this->RegisterPropertyBoolean('EnableAutomaticMode', true);
        $this->RegisterPropertyBoolean('EnableSleepMode', true);
        $this->RegisterPropertyInteger('SleepDuration', 12);
        $this->RegisterPropertyBoolean('EnableBlindMode', true);
        $this->RegisterPropertyString('CloseBlind', '[{"LabelCloseBlind":"","Position":0,"UpdateSetpointPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0}]');
        $this->RegisterPropertyString('Timer', '[{"LabelTimer":"","UseSettings":true,"Position":50,"UpdateSetpointPosition":false,"Duration":30,"DurationUnit":1,"OriginPosition":2,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0}]');
        $this->RegisterPropertyString('OpenBlind', '[{"LabelOpenBlind":"","Position":100,"UpdateSetpointPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0}]');
        $this->RegisterPropertyBoolean('EnableBlindSlider', true);
        $this->RegisterPropertyBoolean('BlindSliderUpdateSetpointPosition', false);
        $this->RegisterPropertyBoolean('EnablePositionPresets', true);
        $this->RegisterPropertyBoolean('PositionPresetsUpdateSetpointPosition', false);
        $this->RegisterPropertyString('PositionPresets', '[{"Value":0,"Text":"0 %"},{"Value":25,"Text":"25 %"}, {"Value":50,"Text":"50 %"},{"Value":75,"Text":"75 %"},{"Value":100,"Text":"100 %"}]');
        $this->RegisterPropertyBoolean('EnableSetpointPosition', true);
        $this->RegisterPropertyBoolean('EnableLastPosition', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowStatus', true);
        $this->RegisterPropertyBoolean('EnableBlindModeTimer', true);
        $this->RegisterPropertyBoolean('EnableSleepModeTimer', true);
        $this->RegisterPropertyBoolean('EnableNextSwitchingTime', true);
        $this->RegisterPropertyBoolean('EnableSunrise', true);
        $this->RegisterPropertyBoolean('EnableSunset', true);
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableIsDay', true);
        $this->RegisterPropertyBoolean('EnableTwilight', true);
        $this->RegisterPropertyBoolean('EnablePresence', true);
        // Actuator
        $this->RegisterPropertyInteger('Actuator', 0);
        $this->RegisterPropertyInteger('DeviceType', 0);
        $this->RegisterPropertyInteger('ActuatorProperty', 0);
        $this->RegisterPropertyInteger('ActuatorBlindPosition', 0);
        $this->RegisterPropertyInteger('ActuatorActivityStatus', 0);
        $this->RegisterPropertyInteger('ActuatorControl', 0);
        // Door and window status
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyString('DoorWindowOpenAction', '[{"LabelOpened":"","SelectPosition":0,"DefinedPosition":0,"UpdateSetpointPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0}]');
        $this->RegisterPropertyString('DoorWindowCloseAction', '[{"LabelClosed":"","SelectPosition":0,"DefinedPosition":0,"UpdateSetpointPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0}]');
        // Switching times
        $this->RegisterPropertyString('SwitchingTimeOne', '[{"LabelSwitchingTime":"","UseSettings":false,"SwitchingTime":"{\"hour\":0,\"minute\":0,\"second\":0}","Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('SwitchingTimeTwo', '[{"LabelSwitchingTime":"","UseSettings":false,"SwitchingTime":"{\"hour\":0,\"minute\":0,\"second\":0}","Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('SwitchingTimeThree', '[{"LabelSwitchingTime":"","UseSettings":false,"SwitchingTime":"{\"hour\":0,\"minute\":0,\"second\":0}","Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('SwitchingTimeFour', '[{"LabelSwitchingTime":"","UseSettings":false,"SwitchingTime":"{\"hour\":0,\"minute\":0,\"second\":0}","Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        // Sunrise and sunset
        $this->RegisterPropertyString('Sunrise', '[{"LabelSunrise":"","UseSettings":false,"ID":0,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('Sunset', '[{"LabelSunset":"","UseSettings":false,"ID":0,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        // Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        $this->RegisterPropertyString('WeeklyScheduleActionOne', '[{"LabelWeeklyScheduleAction":"","UseSettings":false,"ID":0,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('WeeklyScheduleActionTwo', '[{"LabelWeeklyScheduleAction":"","UseSettings":false,"ID":0,"Position":100,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('WeeklyScheduleActionThree', '[{"LabelWeeklyScheduleAction":"","UseSettings":false,"ID":0,"Position":60,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0}]');
        // Is day
        $this->RegisterPropertyInteger('IsDay', 0);
        $this->RegisterPropertyString('NightAction', '[{"LabelNightAction":"","UseSettings":false,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckTwilight":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('DayAction', '[{"LabelDayAction":"","UseSettings":false,"Position":100,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckTwilight":0,"CheckPresence":0}]');
        // Twilight
        $this->RegisterPropertyInteger('TwilightStatus', 0);
        $this->RegisterPropertyString('TwilightDayAction', '[{"LabelTwilightDayAction":"","UseSettings":false,"Position":100,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckPresence":0}]');
        $this->RegisterPropertyString('TwilightNightAction', '[{"LabelTwilightNightAction":"","UseSettings":false,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckPresence":0}]');
        // Presence and absence
        $this->RegisterPropertyInteger('PresenceStatus', 0);
        $this->RegisterPropertyString('AbsenceAction', '[{"LabelAbsenceAction":"","UseSettings":false,"Position":0,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0}]');
        $this->RegisterPropertyString('PresenceAction', '[{"LabelPresenceAction":"","UseSettings":false,"Position":100,"UpdateSetpointPosition":false,"ExecutionDelay":0,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0}]');
        // Triggers
        $this->RegisterPropertyString('Triggers', '[]');
        // Emergency triggers
        $this->RegisterPropertyString('EmergencyTriggers', '[]');
    }

    private function CreateProfiles(): void
    {
        // Automatic mode
        $profile = 'KRS.' . $this->InstanceID . '.AutomaticMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Execute', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Clock', 0x00FF00);
        // Sleep mode
        $profile = 'KRS.' . $this->InstanceID . '.SleepMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Sleep', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Sleep', 0x00FF00);
        // Blind mode
        $profile = 'KRS.' . $this->InstanceID . '.BlindMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Shutter');
        IPS_SetVariableProfileAssociation($profile, 0, 'Schließen', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 1, 'Timer', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Öffnen', '', 0x00FF00);
        // Position presets
        $profile = 'KRS.' . $this->InstanceID . '.PositionPresets';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Menu');
        // Door and window status
        $profile = 'KRS.' . $this->InstanceID . '.DoorWindowStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', 'Window', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', 'Window', 0x0000FF);
    }

    private function UpdatePositionPresets(): void
    {
        // Position presets
        $profile = 'KRS.' . $this->InstanceID . '.PositionPresets';
        $associations = IPS_GetVariableProfile($profile)['Associations'];
        if (!empty($associations)) {
            foreach ($associations as $association) {
                // Delete
                IPS_SetVariableProfileAssociation($profile, $association['Value'], '', '', -1);
            }
        }
        $positionPresets = json_decode($this->ReadPropertyString('PositionPresets'));
        if (!empty($positionPresets)) {
            foreach ($positionPresets as $preset) {
                // Create
                IPS_SetVariableProfileAssociation($profile, $preset->Value, $preset->Text, '', -1);
            }
        }
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AutomaticMode', 'SleepMode', 'BlindMode', 'PositionPresets', 'DoorWindowStatus'];
        foreach ($profiles as $profile) {
            $profileName = 'KRS.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Automatic mode
        $profile = 'KRS.' . $this->InstanceID . '.AutomaticMode';
        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', $profile, 0);
        $this->EnableAction('AutomaticMode');

        // Sleep mode
        $profile = 'KRS.' . $this->InstanceID . '.SleepMode';
        $this->RegisterVariableBoolean('SleepMode', 'Ruhe-Modus', $profile, 1);
        $this->EnableAction('SleepMode');

        // Blind mode
        $profile = 'KRS.' . $this->InstanceID . '.BlindMode';
        $this->RegisterVariableInteger('BlindMode', 'Rollladen', $profile, 2);
        $this->EnableAction('BlindMode');

        // Blind slider
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('BlindSlider', 'Rollladenposition', $profile, 3);
        IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');
        $this->EnableAction('BlindSlider');

        // Position presets
        $profile = 'KRS.' . $this->InstanceID . '.PositionPresets';
        $this->RegisterVariableInteger('PositionPresets', 'Position Voreinstellungen', $profile, 4);
        $this->EnableAction('PositionPresets');

        // Setpoint position
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('SetpointPosition', 'Soll-Position', $profile, 5);
        IPS_SetIcon($this->GetIDForIdent('SetpointPosition'), 'Information');

        // Last position
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('LastPosition', 'Letzte Position', $profile, 6);
        IPS_SetIcon($this->GetIDForIdent('LastPosition'), 'Information');

        // Door and window status
        $profile = 'KRS.' . $this->InstanceID . '.DoorWindowStatus';
        $this->RegisterVariableBoolean('DoorWindowStatus', 'Tür- / Fensterstatus', $profile, 7);

        // Blind mode timer
        $this->RegisterVariableString('BlindModeTimer', 'Rollladenposition bis', '', 8);
        IPS_SetIcon($this->GetIDForIdent('BlindModeTimer'), 'Clock');

        // Sleep mode timer
        $this->RegisterVariableString('SleepModeTimer', 'Ruhe-Modus Timer', '', 9);
        IPS_SetIcon($this->GetIDForIdent('SleepModeTimer'), 'Clock');

        // Next switching time
        $this->RegisterVariableString('NextSwitchingTime', 'Nächste Schaltzeit', '', 10);
        IPS_SetIcon($this->GetIDForIdent('NextSwitchingTime'), 'Information');
    }

    private function CreateLinks(): void
    {
        // Sunrise
        $targetID = 0;
        $sunrise = json_decode($this->ReadPropertyString('Sunrise'), true)[0];
        if (!empty($sunrise)) {
            if ($sunrise['UseSettings']) {
                $targetID = $sunrise['ID'];
            }
        }
        $linkID = @IPS_GetLinkIDByName('Nächster Sonnenaufgang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 11);
            IPS_SetName($linkID, 'Nächster Sonnenaufgang');
            IPS_SetIcon($linkID, 'Sun');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Sunset
        $targetID = 0;
        $sunrise = json_decode($this->ReadPropertyString('Sunset'), true)[0];
        if (!empty($sunrise)) {
            if ($sunrise['UseSettings']) {
                $targetID = $sunrise['ID'];
            }
        }
        $linkID = @IPS_GetLinkIDByName('Nächster Sonnenuntergang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 12);
            IPS_SetName($linkID, 'Nächster Sonnenuntergang');
            IPS_SetIcon($linkID, 'Moon');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Weekly schedule
        $targetID = $this->ReadPropertyInteger('WeeklySchedule');
        $linkID = @IPS_GetLinkIDByName('Nächstes Wochenplanereignis', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 13);
            IPS_SetName($linkID, 'Nächstes Wochenplanereignis');
            IPS_SetIcon($linkID, 'Calendar');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Is day
        $targetID = $this->ReadPropertyInteger('IsDay');
        $linkID = @IPS_GetLinkIDByName('Ist es Tag', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 14);
            IPS_SetName($linkID, 'Ist es Tag');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Twilight
        $targetID = $this->ReadPropertyInteger('TwilightStatus');
        $linkID = @IPS_GetLinkIDByName('Dämmerungsstatus', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 15);
            IPS_SetName($linkID, 'Dämmerungsstatus');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Presence
        $targetID = $this->ReadPropertyInteger('PresenceStatus');
        $linkID = @IPS_GetLinkIDByName('Anwesenheitsstatus', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 16);
            IPS_SetName($linkID, 'Anwesenheitsstatus');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
    }

    private function SetOptions(): void
    {
        // Automatic mode
        IPS_SetHidden($this->GetIDForIdent('AutomaticMode'), !$this->ReadPropertyBoolean('EnableAutomaticMode'));
        // Sleep mode
        IPS_SetHidden($this->GetIDForIdent('SleepMode'), !$this->ReadPropertyBoolean('EnableSleepMode'));
        // Blind Mode
        IPS_SetHidden($this->GetIDForIdent('BlindMode'), !$this->ReadPropertyBoolean('EnableBlindMode'));
        // Blind mode timer
        $useSettings = json_decode($this->ReadPropertyString('Timer'), true)[0]['UseSettings'];
        $profile = 'KRS.' . $this->InstanceID . '.BlindMode';
        if (!$useSettings) {
            // Delete
            IPS_SetVariableProfileAssociation($profile, 1, '', '', -1);
        } else {
            IPS_SetVariableProfileAssociation($profile, 1, 'Timer', '', 0xFFFF00);
        }
        // Blind slider
        IPS_SetHidden($this->GetIDForIdent('BlindSlider'), !$this->ReadPropertyBoolean('EnableBlindSlider'));
        // Position presets
        IPS_SetHidden($this->GetIDForIdent('PositionPresets'), !$this->ReadPropertyBoolean('EnablePositionPresets'));
        // Setpoint position
        IPS_SetHidden($this->GetIDForIdent('SetpointPosition'), !$this->ReadPropertyBoolean('EnableSetpointPosition'));
        // Last position
        IPS_SetHidden($this->GetIDForIdent('LastPosition'), !$this->ReadPropertyBoolean('EnableLastPosition'));
        // Door and window status
        IPS_SetHidden($this->GetIDForIdent('DoorWindowStatus'), !$this->ReadPropertyBoolean('EnableDoorWindowStatus'));
        // Blind mode timer
        IPS_SetHidden($this->GetIDForIdent('BlindModeTimer'), !$this->ReadPropertyBoolean('EnableBlindModeTimer'));
        // Sleep mode timer
        IPS_SetHidden($this->GetIDForIdent('SleepModeTimer'), !$this->ReadPropertyBoolean('EnableSleepModeTimer'));
        // Next switching time
        $hide = !$this->ReadPropertyBoolean('EnableNextSwitchingTime');
        if (!$hide) {
            $properties = ['SwitchingTimeOne', 'SwitchingTimeTwo', 'SwitchingTimeThree', 'SwitchingTimeFour'];
            $hide = true;
            foreach ($properties as $property) {
                $use = json_decode($this->ReadPropertyString($property), true)[0]['UseSettings'];
                if ($use) {
                    $hide = false;
                }
            }
        }
        IPS_SetHidden($this->GetIDForIdent('NextSwitchingTime'), $hide);
        // Sunrise
        $id = @IPS_GetLinkIDByName('Nächster Sonnenaufgang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $settings = json_decode($this->ReadPropertyString('Sunrise'), true)[0];
            $targetID = $settings['ID'];
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($settings['UseSettings']) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Sunset
        $id = @IPS_GetLinkIDByName('Nächster Sonnenuntergang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $settings = json_decode($this->ReadPropertyString('Sunset'), true)[0];
            $targetID = $settings['ID'];
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($settings['UseSettings']) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Weekly schedule
        $id = @IPS_GetLinkIDByName('Nächstes Wochenplanereignis', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule')) {
                if ($this->ValidateWeeklySchedule()) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Is day
        $id = @IPS_GetLinkIDByName('Ist es Tag', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('IsDay');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                $profile = 'Location.' . $targetID . '.IsDay';
                if (!IPS_VariableProfileExists($profile)) {
                    IPS_CreateVariableProfile($profile, 0);
                    IPS_SetVariableProfileAssociation($profile, 0, 'Es ist Nacht', 'Moon', 0x0000FF);
                    IPS_SetVariableProfileAssociation($profile, 1, 'Es ist Tag', 'Sun', 0xFFFF00);
                    IPS_SetVariableCustomProfile($targetID, $profile);
                }
                if ($this->ReadPropertyBoolean('EnableIsDay')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Twilight
        $id = @IPS_GetLinkIDByName('Dämmerungsstatus', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('TwilightStatus');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnableTwilight')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Presence
        $id = @IPS_GetLinkIDByName('Anwesenheitsstatus', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('PresenceStatus');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnablePresence')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
    }

    public function CreateScriptExample(): void
    {
        $scriptID = IPS_CreateScript(0);
        IPS_SetName($scriptID, 'Beispielskript (Komfort-Rollladensteuerung #' . $this->InstanceID . ')');
        $scriptContent = "<?php\n\n// Methode:\n// KRS_MoveBlind(integer \$InstanceID, integer \$Position, integer \$Duration, integer \$DurationUnit);\n\n### Beispiele:\n\n// Rollladen auf 0% schließen:\nKRS_MoveBlind(" . $this->InstanceID . ", 0, 0, 0);\n\n// Rollladen für 180 Sekunden öffnen:\nKRS_MoveBlind(" . $this->InstanceID . ", 100, 180, 0);\n\n// Rollladen für 5 Minuten öffnen:\nKRS_MoveBlind(" . $this->InstanceID . ", 100, 5, 1);\n\n// Rollladen auf 70% öffnen:\nKRS_MoveBlind(" . $this->InstanceID . ', 70, 0, 0);';
        IPS_SetScriptContent($scriptID, $scriptContent);
        IPS_SetParent($scriptID, $this->InstanceID);
        IPS_SetPosition($scriptID, 100);
        IPS_SetHidden($scriptID, true);
        if ($scriptID != 0) {
            echo 'Beispielskript wurde erfolgreich erstellt!';
        }
    }

    private function RegisterSleepModeTimer(): void
    {
        $this->RegisterTimer('SleepMode', 0, 'KRS_DeactivateSleepModeTimer(' . $this->InstanceID . ');');
    }

    private function SetSleepModeTimer(): void
    {
        $this->SetValue('SleepMode', true);
        // Duration from hours to seconds
        $duration = $this->ReadPropertyInteger('SleepDuration') * 60 * 60;
        // Set timer interval
        $this->SetTimerInterval('SleepMode', $duration * 1000);
        $timestamp = time() + $duration;
        $this->SetValue('SleepModeTimer', date('d.m.Y, H:i:s', ($timestamp)));
    }

    public function DeactivateSleepModeTimer(): void
    {
        $this->SetValue('SleepMode', false);
        $this->SetTimerInterval('SleepMode', 0);
        $this->SetValue('SleepModeTimer', '-');
    }

    /**
     * Updates the blind slider.
     */
    private function UpdateBlindSlider(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $actualPosition = intval($this->GetActualBlindPosition());
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualPosition . '%.', 0);
            $this->SetValue('BlindSlider', $actualPosition);
            if ($this->ReadPropertyBoolean('BlindSliderUpdateSetpointPosition')) {
                $this->SetValue('SetpointPosition', $actualPosition);
            }
            $this->SetValue('LastPosition', $actualPosition);
        }
    }
}