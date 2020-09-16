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


namespace Civi;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class RemoteEvent
 *
 * @package Civi\RemoteEvent\Event
 *
 * Abstract event class to provide some basic functions
 */
abstract class RemoteEvent extends Event
{
    /**
     * Get the parameters of the original query
     *
     * @return array
     *   parameters of the query
     */
    public abstract function getQueryParameters();

    /**
     * Add a debug message to the event, so it's easier to find out what happened
     *
     * @param string $message
     *  the debug message
     *
     * @param string $origin
     *  where does this message come from. defaults to file:line_nr
     */
    public function logMessage($message, $origin = null) {
        if (!$origin) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
            $origin = $backtrace[0]['file'];
        }

        // todo: collect? return?
        \Civi::log()->debug("RemoteEvent({$origin}): {$message}");
    }

    /**
     * Get the contact ID if a valid remote_contact_id is involved with this event
     *
     * Warning: this function is cached
     *
     * @return integer|null
     *   the contact ID if a valid id was passed
     */
    public function getRemoteContactID()
    {
        static $remote_contact_id = false;
        if ($remote_contact_id === false) {
            $data = $this->getQueryParameters();
            if (empty($data['remote_contact_id'])) {
                $remote_contact_id = null;
            } else {
                $remote_contact_id = \CRM_Remotetools_Contact::getByKey($data['remote_contact_id']);
            }
        }
        return $remote_contact_id;
    }

    /**
     * Get the currently used locale
     *
     * @return \CRM_Remoteevent_Localisation
     *   the currently used localisation object
     */
    public function getLocalisation()
    {
        $data = $this->getQueryParameters();
        if (empty($data['locale'])) {
            return \CRM_Remoteevent_Localisation::getLocalisation('default');
        } else {
            return \CRM_Remoteevent_Localisation::getLocalisation($data['locale']);
        }
    }
}