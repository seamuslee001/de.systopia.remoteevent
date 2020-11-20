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

use \Civi\RemoteParticipant\Event\CancelEvent as CancelEvent;
use CRM_Remoteevent_ExtensionUtil as E;

/**
 * RemoteParticipant.cancel specification
 * @param array $spec
 *   API specification blob
 */
function _civicrm_api3_remote_participant_cancel_spec(&$spec)
{
    $spec['event_id']          = [
        'name'         => 'event_id',
        'api.required' => 0,
        'title'        => E::ts('Event ID'),
        'description'  => E::ts('Internal ID of the event the registration form is needed for'),
    ];
    $spec['remote_contact_id'] = [
        'name'         => 'remote_contact_id',
        'api.required' => 0,
        'title'        => E::ts('Remote Contact ID'),
        'description'  => E::ts('You can cancel with a remote contact ID, to determine the CiviCRM contact'),
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
 * RemoteParticipant.cancel: Will process the submission/registration data
 *
 * @param array $params
 *   API call parameters
 *
 * @return array
 *   API3 response
 */
function civicrm_api3_remote_participant_cancel($params)
{
    unset($params['check_permissions']);

    // there is two options here to identify the participant object
    // 1) event_id and remote_contact_id
    $participants = [];
    $event_id = (int) CRM_Utils_Array::value('event_id', $params, 0);
    $remote_contact_id = CRM_Utils_Array::value('remote_contact_id', $params);
    if ($event_id && $remote_contact_id) {
        $contact_id = CRM_Remotetools_Contact::getByKey($remote_contact_id);
        if (empty($contact_id)) {
            return civicrm_api3_create_error('Invalid remote_contact_id');
        }
        $participants = CRM_Remoteevent_Registration::getRegistrations($event_id, $contact_id);
    }

    // 2) evaluate token
    if (!empty($params['token'])) {
        $participant_id = CRM_Remotetools_SecureToken::decodeEntityToken(
            'Participant',
            $params['token'],
            'cancel');
        if (!$participant_id) {
            return civicrm_api3_create_error('Invalid Token!');
        } else {
            // load registrations
            $query = civicrm_api3('Participant', 'get', ['id' => $participant_id]);
            $participants = $query['values'];
            if ($participants) {
                $event_id = reset($participants)['id'];
            }
        }
    }

    // check if execute_cancellation is NOT set
    if (!empty($params['probe'])) {
        // this is the 'probe' mode: get information, don't cancel anything yet
        $null = null;
        return civicrm_api3_create_success($participants, $params, 'RemoteParticipant', 'cancel', $null, ['event_id' => $event_id]);
    }

    // pick the one that we want to cancel
    $cancellation_event = new CancelEvent($params, $participants);
    Civi::dispatcher()->dispatch('civi.remoteevent.registration.cancel', $cancellation_event);

    // now simply take out the ones that we should cancel
    $cancellations = $cancellation_event->getParticipantCancellations();

    // at this point we should have at least one
    if (empty($cancellations)) {
        return civicrm_api3_create_error('No participants found to cancel');
    }

    // execute the cancellations
    foreach ($cancellations as $cancellation) {
        civicrm_api3('Participant', 'create', $cancellation);
    }

    return $cancellation_event->createAPI3Success('RemoteParticipant', 'cancel');
}
