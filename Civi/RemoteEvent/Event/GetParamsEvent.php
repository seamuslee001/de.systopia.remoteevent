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

namespace Civi\RemoteEvent\Event;
use Civi\RemoteEvent;

/**
 * Class GetParamsEvent
 *
 * @package Civi\RemoteEvent\Event
 *
 * This event will be triggered at the beginning of the
 *  RemoteEvent.get API call, so the search parameters can be manipulated
 */
class GetParamsEvent extends RemoteEvent
{

    /** @var array holds the original RemoteEvent.get parameters */
    protected $originalParameters;

    /** @var array holds the RemoteEvent.get parameters to be applied */
    protected $currentParameters;

    /** @var integer|false|null remote contact ID if this is a personalised query */
    protected $remote_contact_id;

    public function __construct($params)
    {
        $this->currentParameters  = $params;
        $this->originalParameters = $params;
        $this->remote_contact_id = false; // i.e. not looked up yet
        $this->token_usages = ['invite', 'cancel', 'update'];
    }

    /**
     * Set a parameter for the current parameters
     *
     * @param string $key
     *    parameter key
     * @param mixed $value
     *    parmeter value
     */
    public function setParameter($key, $value)
    {
        $this->currentParameters[$key] = $value;
    }

    /**
     * Returns the original parameters that were submitted to RemoteEvent.get
     *
     * @return array original parameters
     */
    public function getOriginalParameters()
    {
        return $this->originalParameters;
    }

    /**
     * Returns the current (manipulated) parameters to be submitted to Event.get
     *
     * @return array current parameters
     */
    public function getParameters()
    {
        return $this->currentParameters;
    }

    /**
     * Returns the current (manipulated) parameter
     *
     * @param string $key
     *   the parameter key
     *
     * @return mixed|null
     */
    public function getParameter($key)
    {
        return \CRM_Utils_Array::value($key, $this->currentParameters, null);
    }

    /**
     * Get the parameters of the original query
     *
     * @return array
     *   parameters of the query
     */
    public function getQueryParameters()
    {
        return $this->currentParameters;
    }

    /**
     * Get the limit parameter of the original reuqest
     *
     * @return integer
     *   returned result count or 0 for 'no limit'
     */
    public function getOriginalLimit()
    {
        // check the options array
        if (isset($this->originalParameters['options']['limit'])) {
            return (int) $this->originalParameters['options']['limit'];
        }

        // check the old-fashioned parameter style
        if (isset($this->originalParameters['option.limit'])) {
            return (int) $this->originalParameters['option.limit'];
        }

        // default is '25' (by general API contract)
        return 25;
    }

    /**
     * Set the query limit
     *
     * @param $limit integer
     *   the new query limit
     */
    public function setLimit($limit)
    {
        unset($this->currentParameters['option.limit']);
        unset($this->currentParameters['options']['limit']);
        $this->currentParameters['option.limit'] = (int) $limit;
    }

    /**
     * Get the current restriction of event IDs
     *
     * Remark: this returns
     *   null  if not set
     *   fail  if couldn't be parsed
     *   array with a list of event IDs, if everything's fine
     *
     * @return array|null|string
     *   list of requested IDs
     */
    public function getRequestedEventIDs()
    {
        if (isset($this->currentParameters['id'])) {
            $id_param = $this->currentParameters['id'];
            if (is_string($id_param)) {
                // this is a single integer, or a list of integers
                $id_list = explode(',', $id_param);
                return array_map('intval', $id_list);

            } else if (is_array($id_param)) {
                // this is an array. we can deal with the 'IN' => [] notation
                if (count($id_param) == 2) {
                    if (strtolower($id_param[0]) == 'in' && is_array($id_param[1])) {
                        // this should be a list of IDs
                        return array_map('intval', $id_param[1]);
                    }
                }
            }

            // if we get here, we couldn't parse it
            \Civi::log()->debug("RemoteEvent.get: couldn't parse 'id' parameter: " . json_encode($id_param));
            return 'fail';

        } else {
            // 'id' field not set
            return null;
        }
    }

    /**
     * Restrict the query to the given event IDs.
     *  Existing restrictions will be taken into account (intersection)
     *
     * @param array $event_ids
     *   list of event IDs
     */
    public function restrictToEventIds($event_ids)
    {
        if (empty($event_ids)) {
            // this basically means: restrict to empty set:
            $this->currentParameters['id'] = 0;
        } else {
            $current_restriction = $this->getRequestedEventIDs();
            if ($current_restriction === null) {
                // no restriction set so far
                $this->currentParameters['id'] = ['IN' => $event_ids];

            } else if (is_array($current_restriction)) {
                // there is a restriction -> intersect
                $intersection = array_intersect($current_restriction, $event_ids);
                $this->currentParameters['id'] = ['IN' => $intersection];

            } else {
                // something's wrong here
                \Civi::log()->debug("RemoteEvent.get: couldn't restrict 'id' parameter: " . json_encode($current_restriction));
            }
        }
    }
}
