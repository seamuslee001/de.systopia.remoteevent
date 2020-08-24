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

use \Civi\RemoteParticipant\Event\RegistrationEvent as RegistrationEvent;
use CRM_Remoteevent_ExtensionUtil as E;

/**
 * RemoteParticipant.submit specification
 * @param array $spec
 *   API specification blob
 */
function _civicrm_api3_remote_participant_submit_spec(&$spec)
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
function civicrm_api3_remote_participant_submit($params)
{
    // first: validate (again)
    try {
        $validation_result = civicrm_api3('RemoteParticipant', 'validate', $params);
    } catch (Exception $ex) {
        return civicrm_api3_create_error($ex->getMessage());
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
        $reply = civicrm_api3_create_success($registration_event->getErrors());
        $reply['is_error'] = 1;
        $reply['error_msg'] = E::ts("Registration data incomplete or invalid");
        return $reply;

    } else {
        $registration_transaction->commit();
        // todo: return parameters?
        return civicrm_api3_create_success();
    }
}