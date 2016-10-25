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

t3lib_extMgm::extPath('t3o_ldap', 'Classes/Connectors/LDAP.php');
t3lib_extMgm::extPath('t3o_ldap', 'Classes/Utility/PasswordHashing.php');

/**
 * Password updating facility.
 *
 * @since 1.0.0
 */
class Tx_T3oLdap_Utility_PasswordUpdate {

	/**
	 * Update a password in various places (LDAP, TYPO3)
	 *
	 * @param String $username The username to update the password for
	 * @param String $password Cleartext password to hash and update
	 * @return void
	 */
	public function updatePassword($username, $password)
	{

		$extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3o_ldap']);

		/** @var Tx_T3oLdap_Utility_PasswordHashing $passwordHashing */
		$passwordHashing = t3lib_div::makeInstance('Tx_T3oLdap_Utility_PasswordHashing');
        $values = array();
		$updatedHashTypes = array();

		// Hash password with MD5
		$md5 = $passwordHashing->getPasswordHash($password, 'md5');
		if ($md5 !== false) {
			$values[] = $md5;
			$updatedHashTypes[] = 'MD5';
		}

		// Hash password with SHA1
		$sha = $passwordHashing->getPasswordHash($password, 'sha1');
		if ($sha !== false) {
			$values[] = $sha;
			$updatedHashTypes[] = 'SHA1';
		}

		// Hash password with CRYPT
		$crypt = $passwordHashing->getPasswordHash($password, 'crypt');
		if ($crypt !== false) {
			$values[] = $crypt;
			$updatedHashTypes[] = 'CRYPT';
		}

		// Check if LDAP updates are enabled in extension configuration
		if (intval($extensionConfiguration['enableLdapPasswordUpdates']) === 1) {
			/** @var Tx_T3oLdap_Connectors_Ldap $ldap */
			$ldap = t3lib_div::makeInstance('Tx_T3oLdap_Connectors_Ldap');
			if ($ldap->setLdapPasswords($username, $values)) {
				t3lib_div::sysLog(
					'Password successfully updated (Mechanisms: ' . implode(', ', $updatedHashTypes) . ')',
					't3o_ldap',
					t3lib_div::SYSLOG_SEVERITY_INFO
				);
			}
		}
	}
}