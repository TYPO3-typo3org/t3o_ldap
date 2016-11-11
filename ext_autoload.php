<?php
/*
 * Register necessary class names with autoloader
 */
return array(
    'tx_t3oldap_connectors_ldap' => t3lib_extMgm::extPath('t3o_ldap', 'Classes/Connectors/LDAP.php'),
    'tx_t3oldap_utility_passwordhashing' => t3lib_extMgm::extPath('t3o_ldap', 'Classes/Utility/PasswordHashing.php'),
    'tx_t3oldap_utility_passwordupdate' => t3lib_extMgm::extPath('t3o_ldap', 'Classes/Utility/PasswordUpdate.php'),
    'tx_t3oldap_utility_usercreateupdatedelete' => t3lib_extMgm::extPath('t3o_ldap', 'Classes/Utility/UserCreateUpdateDelete.php'),
    'tx_t3oldap_hooks_datahandlerhook' => t3lib_extMgm::extPath('t3o_ldap', 'Classes/Hooks/DataHandlerHook.php'),
);
