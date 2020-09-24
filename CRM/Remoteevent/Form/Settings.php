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
 * Basic settings page
 */
class CRM_Remoteevent_Form_Settings extends CRM_Core_Form
{
    const SETTINGS = [
        'remote_registration_blocking_status_list',
        'remote_registration_link',
        'remote_registration_modify_link',
        'remote_registration_cancel_link',
    ];

    public function buildQuickForm()
    {
        $this->setTitle(E::ts("Remote Events - General Configuration"));

        $this->add(
            'select',
            'remote_registration_blocking_status_list',
            E::ts("Statuses blocking (re)registration"),
            $this->getNegativeStatusList(),
            false,
            ['class' => 'crm-select2', 'multiple' => 'multiple']
        );

        $this->add(
            'text',
            'remote_registration_link',
            E::ts("Registration Link"),
            ['class' => 'huge']
        );
        $this->addRule('remote_registration_link', E::ts("Please enter a valid URL"), 'url');

        $this->add(
            'text',
            'remote_registration_modify_link',
            E::ts("Registration Modification Link"),
            ['class' => 'huge']
        );
        $this->addRule('remote_registration_modify_link', E::ts("Please enter a valid URL"), 'url');

        $this->add(
            'text',
            'remote_registration_cancel_link',
            E::ts("Registration Cancellation Link"),
            ['class' => 'huge']
        );
        $this->addRule('remote_registration_cancel_link', E::ts("Please enter a valid URL"), 'url');

        $this->addButtons(
            [
                [
                    'type' => 'submit',
                    'name' => E::ts('Save'),
                    'isDefault' => true,
                ],
            ]
        );

        // add defaults
        foreach (self::SETTINGS as $setting_key) {
            $this->setDefaults([$setting_key => Civi::settings()->get($setting_key)]);
        }

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        $values = $this->exportValues();

        foreach (self::SETTINGS as $setting_key) {
            Civi::settings()->set($setting_key, CRM_Utils_Array::value($setting_key, $values));
        }
        CRM_Core_Session::setStatus(E::ts("Configuration Updated"));
        parent::postProcess();
    }

    /**
     * Get a list of negative registration statuses
     *
     * @return array
     *   status id => status label
     */
    public function getNegativeStatusList() {
        $list = [];
        $query = civicrm_api3('ParticipantStatusType', 'get', [
            'option.limit' => 0,
            'class'        => 'Negative',
            'return'       => 'id,label'
        ]);
        foreach ($query['values'] as $status) {
            $list[$status['id']] = $status['label'];
        }
        return $list;
    }
}