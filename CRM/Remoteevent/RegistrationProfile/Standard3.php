<?php
/*-------------------------------------------------------+
| SYSTOPIA Remote Event Extension                        |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
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
 * Implements profile 'Standard3': Email, prefix, title, first name, last name, postal address and phone",
 */
class CRM_Remoteevent_RegistrationProfile_Standard3 extends CRM_Remoteevent_RegistrationProfile_Standard2
{
    /**
     * Get the internal name of the profile represented
     *
     * @return string name
     */
    public function getName()
    {
        return 'Standard3';
    }

    /**
     * @param string $locale
     *   the locale to use, defaults to null (current locale)
     *
     * @return array field specs
     * @see CRM_Remoteevent_RegistrationProfile::getFields()
     *
     */
    public function getFields($locale = null)
    {
        $l10n = CRM_Remoteevent_Localisation::getLocalisation($locale);
        return array_merge(
            parent::getFields($locale),
            [
                'phone'                  => [
                    'name'        => 'phone',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 100,
                    'required'    => 0,
                    'label'       => $l10n->localise('Phone Number'),
                    'description' => $l10n->localise("Participant's Phone Number"),
                    'group_name'  => 'contact_base',
                    'group_label' => $l10n->localise("Contact Data"),
                ],
                'street_address'         => [
                    'name'        => 'street_address',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 10,
                    'required'    => 0,
                    'label'       => $l10n->localise('Street Address'),
                    'description' => $l10n->localise("Participant's street and house number"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
                'supplemental_address_1' => [
                    'name'        => 'supplemental_address_1',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 20,
                    'required'    => 0,
                    'label'       => $l10n->localise('Supplemental Address'),
                    'description' => $l10n->localise("Participant's supplemental address"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
                'supplemental_address_2' => [
                    'name'        => 'supplemental_address_2',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 30,
                    'required'    => 0,
                    'label'       => $l10n->localise('Supplemental Address 2'),
                    'description' => $l10n->localise("Participant's supplemental address"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
                'postal_code'            => [
                    'name'        => 'postal_code',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 40,
                    'required'    => 0,
                    'label'       => $l10n->localise('Postal Code'),
                    'description' => $l10n->localise("Participant's postal code"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
                'city'                   => [
                    'name'        => 'city',
                    'type'        => 'Text',
                    'validation'  => '',
                    'weight'      => 40,
                    'required'    => 0,
                    'label'       => $l10n->localise('City'),
                    'description' => $l10n->localise("Participant's city"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
                'country_id'             => [
                    'name'        => 'country_id',
                    'type'        => 'Select',
                    'options'     => $this->getCountries($locale),
                    'validation'  => '',
                    'weight'      => 40,
                    'required'    => 0,
                    'label'       => $l10n->localise('Country'),
                    'description' => $l10n->localise("Participant's country"),
                    'group_name'  => 'contact_address',
                    'group_label' => $l10n->localise("Contact Address"),
                ],
            ]
        );
    }
}