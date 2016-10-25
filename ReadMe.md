# TYPO3 LDAP Utilities
_EXT:t3o\_ldap_

Extension providing utilities for LDAP user and group updates written on the TYPO3 Server
Admin Team Sprint in Berlin, October 2016.

## Concept / Summary
TYPO3 needs a central LDAP server for plain user management. In upcoming releases
this could be changed to an ID management for TYPO3 infrastructure. For a shorter
»time to market« we decided to ship an extension, that hooks into all known user
management processes on T3O (Create account, update password, reset password, delete
account, double opt in, TYPO3 Backend) instead of providing a full featured stand-alone
ID or user management solution.

## Prerequisites

* PHP5 >= 5.3
* TYPO3 >= 4.5
* PHP-LDAP w/ TLS

## Todo

* Hooks in to EXT:ajaxlogin on create, save or update
* Hooks into image update
* Hook in Password change
* TCE Hooks in Backend for BE Updates of FE User
* Delete User in LDAP (notify consumer systems)
* Groups!
* make LDAP Server configuration TypoScript Objects
