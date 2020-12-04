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
use \Civi\RemoteParticipant\Event\GetCreateParticipantFormEvent;
use \Civi\RemoteParticipant\Event\ValidateEvent;

/**
 * RemoteEvent logic for sessions
 */
class CRM_Remoteevent_EventSessions
{
    /**
     * Add the profile data to the get_form results
     *
     * @param GetCreateParticipantFormEvent $get_form_results
     *      event triggered by the RemoteParticipant.get_form API call
     */
    public static function addSessionFields($get_form_results)
    {
        $l10n = $get_form_results->getLocalisation();
        $full_prefix = $l10n->localise("[FULL] ");

        $event = $get_form_results->getEvent();
        $session_data = CRM_Remoteevent_BAO_Session::getSessions($event['id'], true, $event['start_date']);
        $participant_counts = CRM_Remoteevent_BAO_Session::getParticipantCounts($event['id']);

        if (empty($session_data)) {
            // no sessions
            return;
        }

        // clean up: remove inactive session, add 'full' info
        foreach (array_keys($session_data) as $session_key) {
            $session = &$session_data[$session_key];
            if (empty($session['is_active'])) {
                unset($session_data[$session_key]);
            } else {
                $session['participant_count'] = CRM_Utils_Array::value($session['id'], $participant_counts, 0);
                $session['is_full'] = ($session['participant_count'] >= $session['max_participants']) ? 1 : 0;
            }
        }

        // sort sessions by day and slot
        $sessions_by_day_and_slot = [];
        foreach ($session_data as $session) {
            $session_day = $session['day'];
            $session_slot = empty($session['slot_id']) ? '' : $session['slot_id'];
            $sessions_by_day_and_slot[$session_day][$session_slot][] = $session;
        }

        // start listing fields
        $weight = 200;
        $get_form_results->addFields(['sessions' => [
            'type'           => 'fieldset',
            'name'           => 'sessions',
            'label'          => E::ts("Workshops"),
            'weight'         => $weight,
            'description'    => '',
        ]]);
        foreach ($sessions_by_day_and_slot as $day => $slot_sessions) {
            $weight = $weight + 1;
            foreach ($slot_sessions as $slot_id => $sessions) {
                if (empty($sessions)) continue;

                if ($slot_id) {
                    // add name slot fieldset
                    $slot_name = CRM_Remoteevent_BAO_Session::getSlotLabel($slot_id);
                    $group_name = "day{$day}slot{$slot_id}_group";
                    $group_label = count($sessions_by_day_and_slot) > 1 ? // i.e. multi-day event
                        E::ts("Workshops - Day %1 - %2", [1 => $day, 2 => $slot_name]) :
                        E::ts("Workshops - %1", [1 => $slot_name]);

                    // add group
                    $get_form_results->addFields([$group_name => [
                        'type'           => 'fieldset',
                        'name'           => $group_name,
                        'label'          => $group_label,
                        'weight'         => $weight,
                        'parent'         => 'sessions',
                        'description'    => '',
                     ]]);
                } else {
                    // add open (no slot) fieldset
                    $group_name = "day{$day}_group";
                    $group_label = count($sessions_by_day_and_slot) > 1 ? // i.e. multi-day event
                        E::ts("Workshops - Day %1", [1 => $day]) :
                        E::ts("Workshops");

                    // add group
                    $get_form_results->addFields([$group_name => [
                        'type'           => 'fieldset',
                        'name'           => $group_name,
                        'label'          => $group_label,
                        'weight'         => $weight,
                        'parent'         => 'sessions',
                        'description'    => '',
                    ]]);
                }


                foreach ($sessions as $session) {
                    // enrich the session data
                    $weight += 1;
                    $session['type'] = CRM_Remoteevent_BAO_Session::getSessionTypeLabel($session['type_id']);
                    $session['category'] = CRM_Remoteevent_BAO_Session::getSessionCategoryLabel($session['category_id']);

                    if ($slot_id) {
                        // if this is a (real) slot
                        //   the session participation is mutually exclusive for the sessions in the slot

                        $get_form_results->addFields([
                            "session{$session['id']}" => [
                                'name'                => "day{$day}slot{$slot_id}",
                                'type'                => 'Radio',
                                'weight'              => $weight,
                                'label'               => self::renderSessionLabel($session, $full_prefix),
                                'description'         => self::renderSessionDescriptionShort($session),
                                'parent'              => "day{$day}slot{$slot_id}_group",
                                'disabled'            => empty($session['is_full']) ? 0 : 1,
                                'suffix'              => self::renderSessionDescriptionLong($session),
                                'suffix_display'      => 'dialog',
                                'suffix_dialog_label' => E::ts("Details"),
                                'required'            => 0,
                                ]
                         ]);
                    } else {
                        // no slot assigned
                        $get_form_results->addFields(["session{$session['id']}" => [
                            'name'                => "session{$session['id']}",
                            'type'                => 'Checkbox',
                            'weight'              => $weight,
                            'label'               => self::renderSessionLabel($session, $full_prefix),
                            'description'         => self::renderSessionDescriptionShort($session),
                            'parent'              => "day{$day}_group",
                            'disabled'            => empty($session['is_full']) ? 0 : 1,
                            'suffix'              => self::renderSessionDescriptionLong($session),
                            'suffix_display'      => 'dialog',
                            'suffix_dialog_label' => E::ts("Details"),
                            'required'            => 0,
                        ]]);
                    }
                }
            }
        }
    }


    /**
     * Validate the session registrations:
     *  1) make sure that none of them are booked out (except if the participant is already signed up)
     *  2) make sure they are not at the some time or in the same slot
     *
     * @param ValidateEvent $validationEvent
     *      event triggered by the RemoteParticipant.validate or submit API call
     */
    public static function validateSessionSubmission($validationEvent)
    {
        $event_id = $validationEvent->getEventID();
        if (!$event_id) {
            // this really shouldn't happen
            $validationEvent->addError('Event ID not found.');
            return;
        }

        // check if this even concerns us:
        $requested_session_ids = self::getSubmittedSessionIDs($validationEvent->getQueryParameters());
        if (empty($registered_session_ids)) {
            return;
        }

        // load the event's sessions
        $sessions = CRM_Remoteevent_BAO_Session::getSessions($event_id);

        // load the current
        $registered_session_ids = [];
        $participant_id = $validationEvent->getParticipantID();
        if ($participant_id) {
            $registered_session_ids = CRM_Remoteevent_BAO_Session::getParticipantRegistrations($event_id, $participant_id);
        }

        // now check each if booked out
        foreach ($requested_session_ids as $requested_session_id) {
            if (in_array($requested_session_id, $registered_session_ids)) {
                continue; // we don't need to check, if contact already registered there
            }
        }

    }


    /**
     * Extract the session IDs from the submission,
     *   as generated by the 'addSessionFields' function above
     *
     * @param array $submission
     */
    public static function getSubmittedSessionIDs($submission)
    {
        $session_ids = [];
        foreach ($submission as $key => $value) {
            if (!empty($value) && preg_match('/^session_[0-9]+$/', $key)) {
                $session_ids[] = (int) substr($key, 8);
            }
        }
        return $session_ids;
    }




    /**
     * Render the label for the given session data
     *  in the registration form
     *
     * @param array $session
     *   the session data as produced by the API
     *
     * @return string
     *   session label
     */
    protected static function renderSessionLabel($session, $full_text)
    {
        $start_time = date('H:i', strtotime($session['start_date']));
        $end_time = date('H:i', strtotime($session['end_date']));
        $full_marker = empty($session['is_full']) ? '' : $full_text;
        return "{$full_marker}[{$start_time}-{$end_time}] {$session['title']}";
    }

    /**
     * Render the a short description for the given session data
     *  in the registration form
     *
     * @param array $session
     *   the session data as produced by the API
     *
     * @return string
     *   session label
     */
    protected static function renderSessionDescriptionShort($session)
    {
        // load the template
        static $template = null;
        if ($template === null) {
            $template = 'string:' . file_get_contents(E::path('resources/remote_session_short_description.tpl'));
        }

        // render the template
        $smarty = CRM_Core_Smarty::singleton();
        $smarty->assign('session', $session);
        return trim($smarty->fetch($template));
    }

    /**
     * Render the label for the given session data
     *  in the registration form
     *
     * @param array $session
     *   the session data as produced by the API
     *
     * @return string
     *   session label
     */
    protected static function renderSessionDescriptionLong($session)
    {
        // load the template
        static $template = null;
        if ($template === null) {
            $template = 'string:' . file_get_contents(E::path('resources/remote_session_description.tpl'));
        }

        // render the template
        $smarty = CRM_Core_Smarty::singleton();
        $smarty->assign('session', $session);
        return trim($smarty->fetch($template));
    }

}
