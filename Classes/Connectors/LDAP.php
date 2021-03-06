<?php

/*
 * (c) 2016 by mehrwert intermediale kommunikation GmbH
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * LDAP connector class to update accounts and passwords for LDAP user
 * identified by DN. Passwords may be a multivalue attribute hashed by
 * mechanisms defined in the PasswordHashing class. Currently CRYPT,
 * SHA1 and MD5 are used.
 *
 * @package Typo3\Ldap\Connectors
 * @since 1.0.0
 */
class Tx_T3oLdap_Connectors_Ldap {

    /**
     * LDAP server as IP, hostname or complete URI
     * @var string
     */
    private $ldapServer = '';

    /**
     * LDAP server port (0/389/636)
     * @var int
     */
    private $ldapServerPort = 0;

    /**
     * LDAP version (defaults sto 3)
     * @var int
     */
    private $ldapProtocolVersion = 3;

    /**
     * LDAP admin DN to bind for directory updates
     * @var string
     */
    private $ldapBindDn = '';

    /**
     * Bind password for administrative LDAP bind
     * @var string
     */
    private $ldapBindPassword = '';

    /**
     * LDAP connection resource
     * @var null
     */
    private $ldapConnection = null;

    /**
     * LDAP base DN used to find users in LDAP. May be overridden in
     * extension manager configuration
     * @var string
     */
    private $ldapBaseDnForPasswordChanges = 'ou=people,dc=typo3,dc=org';

    /**
     * TYPO3 extension configuration array
     * @var array
     */
    private $extensionConfiguration = array();

    /**
     * Last LDAP error in this class
     * @var string
     */
    private $lastLdapError = '';

    /**
     * LDAP constructor.
     */
    public function __construct()
    {
        // Disable certificate checks on LDAP TLS
        putenv('LDAPTLS_REQCERT=never');

        // TODO Move to TypoScript configuration object if more than one LDAP server is required per installation
        $this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3o_ldap']);
        $this->ldapServer = trim($this->extensionConfiguration['ldapServer']);
        $this->ldapServerPort = intval($this->extensionConfiguration['ldapServerPort']);
        $this->ldapProtocolVersion = intval($this->extensionConfiguration['ldapProtocolVersion']);
        $this->ldapBindDn = trim($this->extensionConfiguration['ldapBindDn']);
        $this->ldapBindPassword = $this->extensionConfiguration['ldapBindPassword'];
        $this->ldapBaseDnForPasswordChanges = trim($this->extensionConfiguration['ldapBaseDnForPasswordChanges']);

        // Connect and bind
        $this->createLdapConnection();
        $this->ldapBind(
            $this->ldapConnection,
            $this->ldapBindDn,
            $this->ldapBindPassword
        );
    }

    /**
     * Test a LDAP bind using given dn and password. Returns true on success
     * and false on bind failure. Errors are logged to syslog.
     *
     * @param string $dn Complete bind DN
     * @param string $password Password to bind with
     * @return bool
     */
    public function testLdapPassword($dn, $password) {
        $ret = false;
        if ($this->createLdapConnection() === true) {
            if ($this->ldapBind($this->ldapConnection, $dn, $password) === true) {
                $ret = true;
            }
        } else {
            t3lib_div::sysLog('Keine LDAP-Bind mit Nutzerdaten moeglich: ' . ldap_error($this->ldapConnection), 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
        }
        return $ret;
    }

    /**
     * Create LDAP connection and bind with admin credentials. Update passwords for
     * a given user (by $username) for all available mechanisms. Errors are logged
     * to syslog.
     *
     * @param string $username Username for bind
     * @param array $values The password array
     * @return bool
     */
    public function setLdapPasswords($username, $values) {

        $ret = false;

        // Create LDAP connection
        if ($this->createLdapConnection() === true) {
            // Try to bind as admin
            if ($this->ldapBind($this->ldapConnection, $this->ldapBindDn, $this->ldapBindPassword) === true) {

                $dn = $this->getDnForUserName($username);

                // TODO Check if user exists and create if not exists?

                // Finally try to update passwords
                $result = $this->updateLdapAttribute($dn, 'userPassword', $values, true);
                if ($result === false) {
                    t3lib_div::sysLog(ldap_error($this->ldapConnection), 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
                }
            } else {
                t3lib_div::sysLog('Unable to bind to LDAP using: ' . ldap_error($this->ldapConnection), 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
            }
        } else {
            t3lib_div::sysLog('No active LDAP connection available', 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
        }
        return $ret;
    }

    /**
     * Bind to the LDAP directory with the given credentials. Errors are logged to syslog.
     *
     * @param resource $ldapConnection
     * @param String $dn Complete bind DN for LDAP entry to bind with
     * @param String $password The password to use for bind
     * @return bool
     */
    private function ldapBind($ldapConnection, $dn, $password) {
        $ret = false;
        try {
            // Bind to LDAP server
            $ldapBind = @ldap_bind(
                $ldapConnection,
                $dn,
                $password
            );
            // Verify binding
            if ($ldapBind) {
                $ret = true;
            } else {
                throw new RuntimeException('Could not bind to LDAP connection: ' . ldap_error($ldapConnection),
                    1453993540);
            }
        } catch (RuntimeException $e) {
            t3lib_div::sysLog($e->getMessage(), 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
        }
        return $ret;
    }

    /**
     * Update an attribute for the given DN. Errors are logged to syslog.
     *
     * @param String $dn Complete DN for LDAP entry to update attributes for
     * @param string $attribute The name of the attribute
     * @param string|array $attributeValues String or array (for multivalue attributes)
     * @param bool $multiValue Whether or not the attribute should be treated as single or multivalue
     * @return bool
     */
    private function updateLdapAttribute($dn, $attribute, $attributeValues, $multiValue = false) {
        $ret = false;
        if (trim($dn) !== '') {
            $attributes = array();
            if ( is_array($attributeValues) ) {
                foreach ($attributeValues AS $attributeValue) {
                    $attributes[$attribute][] = $attributeValue;
                }
            } else {
                $attributes[$attribute] = $attributeValues;
            }
            $ret = ldap_mod_replace($this->ldapConnection, trim($dn), $attributes);
        }
        return $ret;
    }

    /**
     * Create the LDAP connection and set in global scope on success. Return false on failure.
     * Errors are logged to syslog.
     *
     * @return bool
     */
    private function createLdapConnection() {
        $ret = false;
        $port = intval($this->ldapServerPort);
        try {
            $this->ldapConnection = @ldap_connect($this->ldapServer, ($port > 0 ? $port : null));
            if ($this->ldapConnection) {
                // Set protocol version
                if (ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, $this->ldapProtocolVersion)) {
                    if (ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, 0)) {
                        $ret = true;
                    }
                }
            } else {
                throw new RuntimeException('Could not create LDAP connection: ' . ldap_error($this->ldapConnection),
                    1453993539);
            }
        } catch (RuntimeException $e) {
            t3lib_div::sysLog($e->getMessage(), 't3o_ldap', t3lib_div::SYSLOG_SEVERITY_ERROR);
        }
        return $ret;
    }

    /**
     * Wrap with base DN to provide a valid DN to identify the user in the
     * directory service.
     *
     * @param string $username The username to wrap with the base DN
     * @return string
     */
    private function getDnForUserName($username) {
        $dn = 'uid=' . $username . ',' . $this->ldapBaseDnForPasswordChanges;
        return $dn;
    }

    /**
     * Check if a user exists in LDAP
     *
     * @param String $username The username
     * @return bool
     */
    public function userExists($username) {

        $ret = false;

        $dn = $this->getDnForUserName($username);
        $filter = '(|(objectClass=typo3Person))';
        $attributes = array('sn', 'email', 'ou');
        $searchResult = @ldap_search($this->ldapConnection, $dn, $filter, $attributes);
        if ($searchResult) {
            $info = ldap_get_entries($this->ldapConnection, $searchResult);
            if (intval($info['count']) > 0) {
                $ret = true;
            }
        }

        return $ret;
    }

    /**
     * Update a user in LDAP
     *
     * @param array $userData The user data array
     * @return bool
     */
    public function updateUser($userData) {

        $ret = false;
        $dn = $this->getDnForUserName($userData['username']);

        $ldapUserObject = $this->buildLdapObjectArray($userData);

        $res = ldap_modify(
            $this->ldapConnection,
            $dn,
            $ldapUserObject
        );

        if ( $res === true ) {
            // TODO $this->updateFeUserLastLdapUpdateTimestamp($feUserUid);
            $ret = true;
        } else {
            $this->setLastLdapError(ldap_error($this->ldapConnection));
        }

        return $ret;
    }

    /**
     * Enable a user in LDAP
     *
     * @param string $username Username for DN
     * @return bool
     * @todo Requires refactoring for proper syntax of LDAP enabled/disabled
     */
    public function enableUser($username) {

        $ret = false;
        $dn = $this->getDnForUserName($username);
        $ldapUserObject = array(
            'active' => true
        );
        $res = ldap_modify(
            $this->ldapConnection,
            $dn,
            $ldapUserObject
        );

        if ( $res === true ) {
            // TODO $this->updateFeUserLastLdapUpdateTimestamp($feUserUid);
            $ret = true;
        } else {
            $this->setLastLdapError(ldap_error($this->ldapConnection));
        }

        return $ret;
    }

    /**
     * Delete a user in LDAP
     *
     * @param string $username The username to delete in LDAP
     * @return bool
     */
    public function deleteUser($username) {
        $dn = $this->getDnForUserName($username);
        return ldap_delete($this->ldapConnection, $dn);
    }

    /**
     * Create a user in LDAP
     *
     * @param Integer $feUserUid Front end user uid
     * @param array $userData The user data array
     * @param string $password Clear text user password
     * @return bool
     */
    public function createUser($feUserUid, $userData, $password = '') {

        $ret = false;

        if (empty($userData)) {
            $userData = $this->getUserDataByUid($feUserUid);
            if ($userData !== false) {
                $userData['password'] = $password;
            } else {
                $userData = array();
            }
        }

        $dn = $this->getDnForUserName($userData['username']);

        $ldapUserObject = $this->buildLdapObjectArray($userData);
        $res = ldap_add(
            $this->ldapConnection,
            $dn,
            $ldapUserObject
        );

        if ( $res === true ) {
            $this->updateFeUserLastLdapUpdateTimestamp($feUserUid);
            $ret = true;
        } else {
            $this->setLastLdapError(ldap_error($this->ldapConnection));
        }

        return $ret;
    }

    /**
     * Build the array for LDAP insert or updates.
     *
     * @param array $userData
     * @return array
     */
    private function buildLdapObjectArray($userData) {

        $ldapUserObject = array(
            'objectclass' => array(
                0 => 'top',
                1 => 'person',
                2 => 'typo3Person',
                3 => 'inetOrgPerson'
            )
        );
        if (trim($userData['first_name']) === '' && trim($userData['last_name']) === '' && trim($userData['name']) !== '') {
            $nameParts = t3lib_div::trimExplode(' ', $userData['name']);
            $userData['last_name'] = array_pop($nameParts);
            $userData['first_name'] = implode(' ', $nameParts);
        }

        if ( trim($userData['first_name'] . ' ' . $userData['last_name']) !== '' ) {
            $ldapUserObject['cn'] = trim($userData['first_name'] . ' ' . $userData['last_name']);
        }
        if ( trim($userData['first_name'] . ' ' . $userData['last_name']) !== '' ) {
            $ldapUserObject['displayName'] = trim($userData['first_name'] . ' ' . $userData['last_name']);
        }
        if ( trim($userData['first_name']) !== '' ) {
            $ldapUserObject['givenName'] = trim($userData['first_name']);
        }
        if ( trim($userData['last_name']) !== '' ) {
            $ldapUserObject['sn'] = trim($userData['last_name']);
        }
        if ( trim($userData['address']) !== '' ) {
            $ldapUserObject['street'] = trim($userData['address']);
        }
        if ( trim($userData['zip']) !== '' ) {
            $ldapUserObject['postalCode'] = trim($userData['zip']);
        }
        if ( trim($userData['city']) !== '' ) {
            $ldapUserObject['l'] = trim($userData['city']);
        }
        if ( trim($userData['country']) !== '' ) {
            $countryDetails = $this->getCountryDetailsByCountryName($userData['country']);
            if ($countryDetails !== false) {
                if (trim($countryDetails['cn_iso_2']) !== '') {
                    $ldapUserObject['c'] = trim($countryDetails['cn_iso_2']);
                }
                if (trim($countryDetails['cn_short_en']) !== '') {
                    $ldapUserObject['co'] = trim($countryDetails['cn_short_en']);
                }
            }
        }

        $url = filter_var($userData['www'], FILTER_VALIDATE_URL);
        if ($url !== false) {
            $ldapUserObject['labeledURI'] = $url;
        }
        $email = filter_var($userData['email'], FILTER_VALIDATE_EMAIL);
        if ($email !== false) {
            $ldapUserObject['mail'] = $email;
        }
        if (trim($userData['telephone']) !== '') {
            $ldapUserObject['homePhone'] = trim($userData['telephone']);
        }
        if (trim($userData['fax']) !== '') {
            $ldapUserObject['facsimileTelephoneNumber'] = trim($userData['fax']);
        }

        // update terms and conditions data
        if ($userData['accepted_terms_and_conditions'] === '1') {
            $ldapUserObject['conditionsAccepted'] = 1;
        }
        if ($userData['tac_date_of_acceptance'] > 0) {
            $ldapUserObject['conditionsDate'] = $userData['tac_date_of_acceptance'];
        }
        if ($userData['tac_version'] !== '') {
            $ldapUserObject['conditionsVersion'] = $userData['tac_version'];
        }

        // If the password is not salted, it has been submitted and must be included in the LDAP update
        if ($this->isSaltedPassword($userData['password']) === false) {
            /** @var Tx_T3oLdap_Utility_PasswordHashing $passwordHashing */
            $passwordHashing = t3lib_div::makeInstance('Tx_T3oLdap_Utility_PasswordHashing');
            $ldapUserObject['userPassword'][] = $passwordHashing->getPasswordHash($userData['password'], 'sha1');
            $ldapUserObject['userPassword'][] = $passwordHashing->getPasswordHash($userData['password'], 'crypt');
            $ldapUserObject['userPassword'][] = $passwordHashing->getPasswordHash($userData['password'], 'md5');
        }

        if (trim($ldapUserObject['sn']) === '') {
            $ldapUserObject['sn'] = $userData['username'];
        }
        if (trim($ldapUserObject['cn']) === '') {
            $ldapUserObject['cn'] = $userData['username'];
        }
        if (trim($ldapUserObject['uid']) === '') {
            $ldapUserObject['uid'] = $userData['username'];
        }

        return $ldapUserObject;
    }

    /**
     * Check a given String for salting.
     *
     * @param String $passwordString The password string
     * @return bool
     */
    private function isSaltedPassword($passwordString) {
        $ret = false;
        if ($passwordString !== '') {
            if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
                $objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance($passwordString, 'FE');
                if (is_object($objSalt)) {
                    if ($objSalt->isValidSaltedPW($passwordString)) {
                        $ret = true;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Update the last modified in LDAP timestamp of a user
     *
     * @param $feUserUid
     * @return mixed
     */
    private function updateFeUserLastLdapUpdateTimestamp($feUserUid) {
        return $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'fe_users',
            'uid = ' . intval($feUserUid),
            array(
                'tx_t3oldap_lastupdate_ts' => $GLOBALS['EXEC_TIME']
            )
        );
    }

    /**
     * @return string
     */
    public function getLastLdapError()
    {
        return $this->lastLdapError;
    }

    /**
     * @param string $lastLdapError
     * @return void
     */
    public function setLastLdapError($lastLdapError)
    {
        $this->lastLdapError = $lastLdapError;
    }

    /**
     * @param $countryName
     * @return bool
     */
    private function getCountryDetailsByCountryName($countryName) {

        $ret = false;

        $whereClause = 'cn_short_en LIKE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($countryName, 'static_countries');
        $selectFields = 'uid, cn_iso_2, cn_short_en';
        $fromTable = 'static_countries';
        $groupBy = '';
        $orderBy = '';
        $limit = '1';

        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);
        if ( $result ) {
            if ( $GLOBALS['TYPO3_DB']->sql_num_rows($result) == 1 ) {
                $ret = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
                $GLOBALS['TYPO3_DB']->sql_free_result($result);
            }
        }

        return $ret;

    }

    /**
     * Get details of a fe_user
     *
     * @param $feUserUid
     * @return Mixed bool|array
     */
    private function getUserDataByUid($feUserUid) {

        $ret = false;

        $whereClause = 'uid = ' . intval($feUserUid);
        $selectFields = 'uid, username, first_name, last_name, name, address, zip, city, country, www, email, telephone, fax, tac_version, tac_date_of_acceptance, accepted_terms_and_conditions';
        $fromTable = 'fe_users';
        $groupBy = '';
        $orderBy = '';
        $limit = '1';

        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);
        if ( $result ) {
            if ( $GLOBALS['TYPO3_DB']->sql_num_rows($result) == 1 ) {
                $ret = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
                $GLOBALS['TYPO3_DB']->sql_free_result($result);
            }
        }

        return $ret;

    }

    /**
     * Destroy the LDAP connection
     */
    public function __destruct()
    {
        if ($this->ldapConnection) {
            ldap_close($this->ldapConnection);
        }
    }

}