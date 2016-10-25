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
 * Password hashing facility. Mechanisms defined are CRYPT,
 * SHA1 and MD5.
 */
class Tx_T3oLdap_Utility_PasswordHashing {

	/**
	 * Hash a given string with the mechanism defined. Return the hash.
	 *
	 * @param string $clearText Cleartext representation of the password
	 * @param string $algorithm The hashing mechanism
	 * @param string $salt Optional salt
	 * @return bool|string False on failure or the hashed password as string
	 */
	public function getPasswordHash($clearText, $algorithm = 'crypt', $salt = 'xy') {
		$ret = false;
		if (trim($clearText) !== '') {
			switch ($algorithm) {
				case 'sha1':
					$passwordHash = sha1($clearText, true);
					$ret = '{SHA}' . base64_encode($passwordHash);
					break;
				case 'md5':
					$passwordHash = md5($clearText, true);
					$ret = '{MD5}' . base64_encode($passwordHash);
					break;
				case 'crypt':
					$passwordHash = crypt($clearText, $salt);
					$ret = '{CRYPT}' . $passwordHash;
				default:
			}
		}
		return $ret;
	}
}