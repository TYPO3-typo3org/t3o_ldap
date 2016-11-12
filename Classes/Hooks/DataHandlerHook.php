<?php

/***************************************************************
 * Copyright notice
 *
 * (c) 2016 Andreas Beutel, mehrwert intermediale kommunikation GmbH <typo3@mehrwert.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Hook Functions for \TYPO3\CMS\Core\DataHandling\DataHandler
 */
class Tx_T3oLdap_Hooks_DataHandlerHook {

    /**
     * DataHandlerHook constructor
     */
    public function __construct() {
    }

    /**
     * Use DataHandler "afterAllOperations" hook to update or create FE Users
     * in LDAP.
     *
     * @return void
     */
    public function processDatamap_afterAllOperations(t3lib_TCEmain $dataHandler) {

        try {
            foreach ($dataHandler->datamap as $tableName => $configuration) {
                if ($tableName === 'fe_users') {
                    foreach ($configuration as $feUserUid => $changedFields) {
                        /** @var Tx_T3oLdap_Utility_UserCreateUpdateDelete $userUtility */
                        $userUtility = t3lib_div::makeInstance('Tx_T3oLdap_Utility_UserCreateUpdateDelete');
                        $userUtility->updateUser($feUserUid, $changedFields);
                    }
                }
            }
        }
        catch(Exception $e) {
            /** @var $flashMessage t3lib_FlashMessage */
            $flashMessage = t3lib_div::makeInstance(
                't3lib_FlashMessage',
                'Failed to update users in LDAP: ' . $e->getMessage(),
                'Error in processDatamap_afterAllOperations',
                t3lib_FlashMessage::ERROR
            );
            t3lib_FlashMessageQueue::addMessage($flashMessage);
        }
    }

}