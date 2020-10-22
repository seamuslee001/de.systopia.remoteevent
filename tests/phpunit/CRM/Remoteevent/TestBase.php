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

use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

use CRM_Remoteevent_ExtensionUtil as E;

/**
 * This is the test base class with lots of utility functions
 *
 * @group headless
 */
class CRM_Remoteevent_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface,
                                                                            TransactionalInterface
{
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
    }

    /** @var CRM_Core_Transaction current transaction */
    protected $transaction = null;

    public function setUpHeadless()
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
        return \Civi\Test::headless()
            ->install(['de.systopia.xcm'])
            ->install(['de.systopia.identitytracker'])
            ->install(['de.systopia.remotetools'])
            ->installMe(__DIR__)
            ->apply();
    }

    public function setUp()
    {
        parent::setUp();
        $this->transaction = new CRM_Core_Transaction();
        $this->setUpXCMProfile('default');
    }

    public function tearDown()
    {
        $this->transaction->rollback();
        $this->transaction = null;
        parent::tearDown();
    }

    /**
     * Create a new remote event. All of the
     *  vital fields will have default values, that can be overwritten by
     *  the array passed
     *
     * @param array $event_details
     */
    public function createRemoteEvent($event_details = [])
    {
        // prepare event
        $event_data = [
            'title'                                                         => "Event " . microtime(),
            'event_type_id'                                                 => 1,
            'start_date'                                                    => date('Y-m-d', strtotime('tomorrow')),
            'default_role_id'                                               => 1,
            'is_active'                                                     => 1,
            'event_remote_registration.remote_registration_enabled'         => 1,
            'event_remote_registration.remote_disable_civicrm_registration' => 1,
            'event_remote_registration.remote_registration_default_profile' => 'Standard1',
            'event_remote_registration.remote_registration_profiles'        => ['Standard1'],
        ];
        foreach ($event_details as $key => $value) {
            $event_data[$key] = $value;
        }
        // sanity check: default profile should be enabled
        $default_profile = $event_data['event_remote_registration.remote_registration_default_profile'];
        if (!in_array($default_profile, $event_data['event_remote_registration.remote_registration_profiles'])) {
            $event_data['event_remote_registration.remote_registration_profiles'][] = $default_profile;
        }
        // resolve custom fields
        CRM_Remoteevent_CustomData::resolveCustomFields($event_data);

        // create event and reload
        $result = $this->traitCallAPISuccess('Event', 'create', $event_data);
        $event = $result = $this->traitCallAPISuccess('RemoteEvent', 'getsingle', ['id' => $result['id']]);
        CRM_Remoteevent_CustomData::labelCustomFields($event);

        return $event;
    }

    /**
     * Create a number of events with the same setup,
     *   using the createRemoteEvent function
     *
     * @param integer $count
     * @param array $event_details
     *
     * @return array [event_id => $event_data]
     */
    public function createRemoteEvents($count, $event_details = [])
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $event = $this->createRemoteEvent($event_details);
            $result[$event['id']] = $event;
        }
        return $result;
    }

    /**
     * Register the given contact to the given event
     *
     * @param integer $contact_id
     * @param integer $event_id
     * @param array $participant_details
     */
    public function registerRemote($event_id, $participant_details = [])
    {
        $participant_data = [
            'event_id'   => $event_id,
        ];
        foreach ($participant_details as $key => $value) {
            $participant_data[$key] = $value;
        }

        // register via our API
        try {
            return civicrm_api3('RemoteParticipant', 'submit', $participant_data);
        } catch (CiviCRM_API3_Exception $ex) {
            return civicrm_api3_create_error($ex->getMessage(), ['errors' => $ex->getExtraParams()['errors']]);
        }
    }

    /**
     * Create a new contact
     *
     * @param array $contact_details
     *   overrides the default values
     *
     * @return array
     *  contact data
     */
    public function createContact($contact_details = [])
    {
        // prepare event
        $contact_data = [
            'contact_type' => 'Individual',
            'first_name'   => $this->randomString(10),
            'last_name'    => $this->randomString(10),
            'email'        => $this->randomString(10) . '@' . $this->randomString(10) . '.org',
        ];
        foreach ($contact_details as $key => $value) {
            $contact_data[$key] = $value;
        }
        CRM_Remoteevent_CustomData::resolveCustomFields($contact_data);

        // create contact
        $result = $this->traitCallAPISuccess('Contact', 'create', $contact_data);
        $contact = $this->traitCallAPISuccess('Contact', 'getsingle', ['id' => $result['id']]);
        CRM_Remoteevent_CustomData::labelCustomFields($contact);
        return $contact;
    }

    /**
     * Create a number of new contacts
     *  using the createContact function above
     *
     * @param integer $count
     * @param array $contact_details
     *
     * @return array [event_id => $event_data]
     */
    public function createContacts($count, $contact_details = [])
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $contact = $this->createContact($contact_details);
            $result[$contact['id']] = $contact;
        }
        return $result;

    }

    /**
     * Generate a random string, and make sure we don't collide
     *
     * @param int $length
     *   length of the string
     *
     * @return string
     *   random string
     */
    public function randomString($length = 32)
    {
        static $generated_strings = [];
        $candidate = substr(sha1(random_bytes(32)), 0, $length);
        if (isset($generated_strings[$candidate])) {
            // simply try again (recursively). Is this dangerous? Yes, but veeeery unlikely... :)
            return $this->randomString($length);
        }
        // mark as 'generated':
        $generated_strings[$candidate] = 1;
        return $candidate;
    }

    /**
     * Make sure the given profile exists, and has
     *   a basic amount of matching options
     *
     * @param string $profile_name
     */
    public function setUpXCMProfile($profile_name)
    {
        $profile = CRM_Xcm_Configuration::getConfigProfile($profile_name);

        // add basic rules
        $needed_rules = ['CRM_Xcm_Matcher_EmailMatcher', 'CRM_Xcm_Matcher_FirstNameAddressMatcher'];
        $rules = $profile->getRules();
        foreach ($needed_rules as $needed_rule) {
            if (!in_array($needed_rule, $rules)) {
                $rules[] = $needed_rule;
            }
        }
        $profile->setRules($rules);
        $profile->store();
    }

    /**
     * Set a value for the speaker roles
     *
     * @param array $value
     *   a list of role_ids or empty for disabled
     */
    public function setSpeakerRoles($value)
    {
        Civi::settings()->set('remote_registration_speaker_roles', $value);
    }

    /**
     * Use the RemoteEvent.get API to event information
     *
     * @param array $event_ids
     *   list of event_ids
     */
    public function getRemoteEvents($event_ids)
    {
        $result = $this->traitCallAPISuccess('RemoteEvent', 'get', [
            'id'         => ['IN' => $event_ids],
            'sequential' => 0
        ]);
        return $result['values'];
    }

    /**
     * Use the RemoteEvent.get API to event information
     *
     * @param integer $event_id
     *   event id
     *
     * @param array $params
     *   additional parameters
     */
    public function getRemoteEvent($event_id, $params = [])
    {
        $params['id'] = $event_id;
        return $this->traitCallAPISuccess('RemoteEvent', 'getsingle', $params);
    }

    /**
     * Get a random value subset of the array
     *
     * @param array $array
     *   the array to pick the values
     *
     * @param integer $count
     *   number of elements to pick, will be randomised if not given
     *
     * @return array
     *   subset (keys not retained)
     */
    public function randomSubset($array, $count = null)
    {
        if ($count === null) {
            $count = mt_rand(1, count($array) - 1);
        }

        $random_keys = array_rand($array, $count);
        if (!is_array($random_keys)) {
            $random_keys = [$random_keys];
        }

        // create result array
        $result = [];
        foreach ($random_keys as $random_key) {
            $result[] = $array[$random_key];
        }
        return $result;
    }

    /**
     * Get a remote contact key for the given contact.
     *  if no such key exists, create one
     *
     * @param integer $contact_id
     *
     * @return string
     *   contact key
     */
    public function getRemoteContactKey($contact_id)
    {
        $contact_id = (int) $contact_id;
        $key = CRM_Core_DAO::singleValueQuery("
            SELECT identifier
            FROM civicrm_value_contact_id_history
            WHERE identifier_type = 'remote_contact'
              AND entity_id = {$contact_id}
            LIMIT 1
        ");
        if (!$key) {
            $key = $this->randomString();
            CRM_Core_DAO::executeQuery("
                INSERT INTO civicrm_value_contact_id_history (entity_id, identifier, identifier_type, used_since)
                VALUES ({$contact_id}, '{$key}', 'remote_contact', NOW())
            ");
        }

        $verify_contact_id = CRM_Remotetools_Contact::getByKey($key);
        $this->assertEquals($contact_id, $verify_contact_id, "Couldn't generate remote contact key.");
        return $key;
    }
}