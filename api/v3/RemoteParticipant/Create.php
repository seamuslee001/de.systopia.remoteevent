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

require_once 'remoteevent.civix.php';

use Civi\RemoteEvent;
use \Civi\RemoteParticipant\Event\RegistrationEvent as RegistrationEvent;
use CRM_Remoteevent_ExtensionUtil as E;

/**
 * RemoteParticipant.create specification:
 *   submit a new event registration
 *
 * @param array $spec
 *   API specification blob
 */
function _civicrm_api3_remote_participant_create_spec(&$spec)
{
    $spec['event_id']          = [
        'name'         => 'event_id',
        'api.required' => 1,
        'title'        => E::ts('Event ID'),
        'description'  => E::ts('Internal ID of the event the registration form is needed for'),
    ];
    $spec['profile']           = [
        'name'         => 'profile',
        'api.required' => 0,
        'title'        => E::ts('Profile Name'),
        'description'  => E::ts('If omitted, the default profile is used'),
    ];
    $spec['remote_contact_id'] = [
        'name'         => 'remote_contact_id',
        'api.required' => 0,
        'title'        => E::ts('Remote Contact ID'),
        'description'  => E::ts('You can submit a remote contact ID, to determine the CiviCRM contact'),
    ];
    $spec['token'] = [
        'name'         => 'token',
        'api.required' => 0,
        'title'        => E::ts('Remote Registration Token'),
        'description'  => E::ts('You can cancel with a as generated by emails'),
    ];
    $spec['locale']            = [
        'name'         => 'locale',
        'api.required' => 0,
        'title'        => E::ts('Locale'),
        'description'  => E::ts('Locale of the field labels/etc. NOT IMPLEMENTED YET'),
    ];
}

/**
 * RemoteParticipant.submit: Will process the submission/registration data
 *
 * @param array $params
 *   API call parameters
 *
 * @return array
 *   API3 response
 */
function civicrm_api3_remote_participant_create($params)
{
    unset($params['check_permissions']);

    // first: validate (again)
    try {
        $validation_result = civicrm_api3('RemoteParticipant', 'validate', $params);
    } catch (CiviCRM_API3_Exception $ex) {
        $errors = $ex->getExtraParams()['values'];
        return RemoteEvent::createStaticAPI3Error(reset($errors), ['errors' => $errors]);
    }

    // create a transaction
    $registration_transaction = new CRM_Core_Transaction();

    // dispatch to the various handlers
    $registration_event = new RegistrationEvent($params);
    try {
        Civi::dispatcher()->dispatch('civi.remoteevent.registration.submit', $registration_event);
    } catch (Exception $ex) {
        $registration_event->addError($ex->getMessage());
    }

    // evaluate the result
    if ($registration_event->hasErrors()) {
        // something went wrong...
        $registration_transaction->rollback();
        return $registration_event->createAPI3Error();

    } else {
        $registration_transaction->commit();
        $participant = civicrm_api3('Participant', 'getsingle', ['id' => $registration_event->getParticipantID()]);
        $null = null;

        return $registration_event->createAPI3Success('RemoteParticipant', 'create', 1, [
            'event_id'           => $participant['event_id'],
            'participant_id'     => $participant['id'],
            'participant_role'   => $participant['participant_role'],
            'participant_status' => $participant['participant_status'],
            'participant_class'  => CRM_Remoteevent_Registration::getParticipantStatusClass($participant['participant_status_id'])
        ]);
    }
}
