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
 * Extend class Tx_Ajaxlogin_Controller_UserController to add LDAP hooks
 * for password updates.
 */
class Tx_T3oAjaxlogin_Controller_UserController extends Tx_Ajaxlogin_Controller_UserController
{

    /**
     * @param array $password Associate array with the following keys.
     *                              cur   - Current password
     *                              new   - New password
     *                              check - Confirmed new password
     * @validate $password Tx_Ajaxlogin_Domain_Validator_PasswordsValidator
     * @return string
     */
    public function doChangePasswordAction(array $password)
    {
        $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3o_ldap']);
        $enableLdapPasswordUpdates = intval($extensionConfiguration['enableLdapPasswordUpdates']);

        $errors = array();
        $currentUser = $this->userRepository->findCurrent();

        if (isset($password['cur']) && isset($password['new']) && isset($password['check'])) {
            $plainTextPassword = $password['cur'];
            $encryptedPassword = $currentUser->getPassword();

            if (Tx_Ajaxlogin_Utility_Password::validate($plainTextPassword, $encryptedPassword)) {
                $saltedPassword = Tx_Ajaxlogin_Utility_Password::salt($password['new']);
                $currentUser->setPassword($saltedPassword);

                // Update LDAP Password
                if ( $enableLdapPasswordUpdates === 1 ) {
                    /** @var Tx_T3oLdap_Utility_PasswordUpdate $passwordUpdateUtility */
                    $passwordUpdateUtility = t3lib_div::makeInstance('Tx_T3oLdap_Utility_PasswordUpdate');
                    $passwordUpdateUtility->updatePassword(
                        $currentUser->getUsername(),
                        $password['new']
                    );
                }

                // redirect (if configured) or show static success text
                $redirectPageId = intval($this->settings['page']['passwordChangeSuccess']);
                if ($redirectPageId > 0) {
                    $this->redirectToPage($redirectPageId);
                } else {
                    return Tx_Extbase_Utility_Localization::translate('password_updated', 'ajaxlogin');
                }
            } else {
                $errors['current_password'] = Tx_Extbase_Utility_Localization::translate('password_invalid',
                    'ajaxlogin');
            }
        }

        $this->forward('changePassword', null, null, array('errors' => $errors));
    }

    /**
     * Redirects user to the page identified by the given page-id.
     *
     * @param int $pageId   ID of the page to redirect to.
     */
    private function redirectToPage($pageId) {
        $uri = $this->uriBuilder
            ->reset()
            ->setTargetPageUid($pageId)
            ->build();
        $this->redirectToURI($uri);
    }
}