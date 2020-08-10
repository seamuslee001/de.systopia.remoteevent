<?php
/*-------------------------------------------------------+
| SYSTOPIA CUSTOM DATA HELPER                            |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| Source: https://github.com/systopia/Custom-Data-Helper |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Remoteevent_ExtensionUtil as E;

/**
 * Form controller for event online registration settings
 */
class CRM_Remoteevent_Form_RegistrationConfig extends CRM_Event_Form_ManageEvent
{
    protected $event_id = null;

    public function buildQuickForm()
    {
        // gather data
        $this->event_id = CRM_Utils_Request::retrieve('event_id', 'Integer', $this);
        $available_registration_profiles = CRM_Remoteevent_RegistrationProfile::getAvailableRegistrationProfiles();

        // add form elements
        $this->add(
            'checkbox',
            'remote_registration_enabled',
            E::ts("Remote Registration Enabled")
        );
        $this->add(
            'select',
            'remote_registration_default_profile',
            E::ts("Default Profile"),
            $available_registration_profiles,
            false,
            ['class' => 'crm-select2']
        );
        $this->add(
            'select',
            'remote_registration_profiles',
            E::ts("Allowed Profiles"),
            $available_registration_profiles,
            false,
            ['class' => 'crm-select2', 'multiple' => 'multiple']
        );
        $this->assign('profiles', $available_registration_profiles);

        // load and set defaults
        if ($this->event_id) {
            $field_list = [
                'event_remote_registration.remote_registration_enabled'         => 'remote_registration_enabled',
                'event_remote_registration.remote_registration_default_profile' => 'remote_registration_default_profile',
                'event_remote_registration.remote_registration_profiles'        => 'remote_registration_profiles'
            ];
            CRM_Remoteevent_CustomData::resolveCustomFields($field_list);
            $values = civicrm_api3('Event', 'getsingle', [
                'id'     => $this->event_id,
                'return' => implode(',', array_keys($field_list)),
            ]);
            foreach ($field_list as $custom_key => $form_key) {
                $this->setDefaults([$form_key => CRM_Utils_Array::value($custom_key, $values, '')]);
            }
        }

        $this->addButtons(
            array(
                array(
                    'type'      => 'submit',
                    'name'      => E::ts('Save'),
                    'isDefault' => true,
                ),
            )
        );

        parent::buildQuickForm();
    }

    public function validate()
    {
        parent::validate();
        if (!empty($this->_submitValues['remote_registration_enabled'])) {
            // online registration is enabled, do some checks:
            if (empty($this->_submitValues['remote_registration_default_profile'])) {
                $this->_errors['remote_registration_default_profile'] = E::ts("You must select a default profile");
            }
        }

        return (0 == count($this->_errors));
    }

    public function postProcess()
    {
        $values  = $this->exportValues();

        // todo: make sure default profile is one of the enabled ones

        // store data
        $event_update = [
            'id' => $this->event_id,
            'event_remote_registration.remote_registration_enabled'         => $values['remote_registration_enabled'],
            'event_remote_registration.remote_registration_default_profile' => $values['remote_registration_default_profile'],
            'event_remote_registration.remote_registration_profiles'        => $values['remote_registration_profiles']
        ];
        CRM_Remoteevent_CustomData::resolveCustomFields($event_update);
        civicrm_api3('Event', 'create', $event_update);

        // todo: figure out how to make this work
        $this->_action = CRM_Core_Action::UPDATE;
        $this->_name = 'remoteregistration';
        $this->_id = $this->event_id;
        parent::endPostProcess();
    }
}
