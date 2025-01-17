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

use \Civi\RemoteParticipant\Event\ValidateEvent as ValidateEvent;
use CRM_Remoteevent_ExtensionUtil as E;

/**
 * RemoteParticipant.validate specification
 * @param array $spec
 *   API specification blob
 */
function _civicrm_api3_remote_participant_validate_spec(&$spec)
{
    $spec['context']          = [
        'name'         => 'context',
        'api.default'  => 'create',
        'title'        => E::ts('Context'),
        'description'  => E::ts('Which context/action is the form for (create/cancel/update)'),
    ];
    $spec['event_id']          = [
        'name'         => 'event_id',
        'api.required' => 0,
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
 * RemoteParticipant.submit: Will validate the submission/registration data
 *   and return a list of errors for the given fields
 *
 * @param array $params
 *   API call parameters
 *
 * @return array
 *   API3 response
 *
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_remote_participant_validate($params)
{
    unset($params['check_permissions']);
    $validation = new ValidateEvent($params);

    // identify a given contact ID
    $contact_id = null;
    if (!empty($params['remote_contact_id'])) {
        $contact_id = CRM_Remotetools_Contact::getByKey($params['remote_contact_id']);
        if (!$contact_id) {
            $validation->addError(E::ts("RemoteContactID is invalid"));
        }
    }

    // special case: invitation decline (confirm=0) skips validation
    if (isset($params['confirm']) && $params['confirm'] == '0') {
        return $validation->createAPI3Success('RemoteEvent', 'validate');
    }

    // run by context
    switch ($params['context']) {
        case 'create':
            // first: check if registration is enabled
            $cant_register_reason = CRM_Remoteevent_Registration::cannotRegister($params['event_id'], $contact_id);
            if ($cant_register_reason) {
                $validation->addError($cant_register_reason);
            } else {
                // dispatch the validation event for other validations to weigh in
                Civi::dispatcher()->dispatch('civi.remoteevent.registration.validate', $validation);
            }
            break;

        case 'update':
            Civi::dispatcher()->dispatch('civi.remoteevent.registration.validate', $validation);
            break;

        case 'cancel':
            // todo: implement?
            break;

        default:
            $validation->addError(E::ts("Context '%1' not implemented.", [1 => $params['context']]));
            break;
    }


    // return the result
    return $validation->createAPI3Success('RemoteEvent', 'validate', $validation->getReferencedStatusList(['error']));
}