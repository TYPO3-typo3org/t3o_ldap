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
 * Class to create, update or delete accounts in LDAP
 *
 * @since 1.0.0
 */
class Tx_T3oLdap_Utility_UserCreateUpdateDelete {

    /**
     * Update a LDAP User. If the user does not exist, it will be created.
     *
     * @param integer $feUserUid The frontend user id
     * @param array $userData The submitted data
     * @param boolean $createIfNotExists Create the user if it does not exist
     * @return boolean
     */
    public function updateUser($feUserUid, $userData, $createIfNotExists = true) {

        $ret = false;

        // LDAP Configuration
        $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3o_ldap']);

        /** @var Tx_T3oLdap_Connectors_Ldap $ldap */
        $ldap = t3lib_div::makeInstance('Tx_T3oLdap_Connectors_Ldap');

        if ($ldap->userExists($userData['username'])) {

            $ret = $ldap->updateUser($userData);

            // TODO Delete User in LDAP (notify consumer systems)
            // $ret = $ldap->deleteUser($userData);

        } elseif ($createIfNotExists === true) {
            $ret = $ldap->createUser($feUserUid, $userData);
        }

        return $ret;

    }

}