# @author		mehrwert <typo3@mehrwert.de>
# @package		TYPO3
# @subpackage	tx_t3oldap
# @license		GPL

#
# Table structure for table 'fe_users'
#
CREATE TABLE fe_users (
	tx_t3oldap_pwd_lastupdate_ts int(11) unsigned DEFAULT '0' NOT NULL,
	tx_t3oldap_lastupdate_ts int(11) unsigned DEFAULT '0' NOT NULL,
	tx_t3oldap_pwd_change_required tinyint(3) unsigned DEFAULT '0' NOT NULL,
);