<?php
	#
	# $Id: cache.php,v 1.1.2.7 2006-06-26 12:04:27 dan Exp $
	#
	# Copyright (c) 2006 DVL Software Limited
	#

define('CACHING_LOCATION',  $_SERVER['DOCUMENT_ROOT'] . '/../dynamic/caching/cache');
define('SPOOLING_LOCATION', $_SERVER['DOCUMENT_ROOT'] . '/../dynamic/caching/spool');

// base class for caching
// Supplies methods for adding, removing, and retrieving.
//
class Cache {

	var $CacheDir;
	var $SpoolDir;

	function Cache($CacheDir = CACHING_LOCATION, $SpoolDir = SPOOLING_LOCATION) {
		$this->CacheDir = $CacheDir;
		$this->SpoolDir = $SpoolDir;
	}
	
	function Retrieve($key, &$data) {
		$result = 0;

		$CacheFileName = $this->_CacheFileName($key);
		if (file_exists($CacheFileName) && is_readable($CacheFileName)) {
			// open, read, and return
			$CacheFileHandle = fopen($CacheFileName, 'r');
			if ($CacheFileHandle) {
				$data = fread($CacheFileHandle, filesize ($CacheFileName));
				fclose($CacheFileHandle);
				$this->_Log('Cache: Retrieve ' . $CacheFileName);
			} else {
				$this->_Log('Cache: FAILED Retrieve file open ' . $CacheFileName);
				$result = -1;
			}
		} else {
			$this->_Log('Cache: FAILED Retrieve NOT FOUND ' .$CacheFileName);
			$result = -2;
		}
		
		return $result;
	}

	function Add($key, $data) {
		$result = 0;
		// need to use key name with care.  Remove all non a-z, A-Z, and 0-9.
		$SpoolFileName = $this->_SpoolFileName($key);
		$SpoolFileHandle = fopen($SpoolFileName, 'w');
		if ($SpoolFileHandle) {
			// write $data to file
			if (fwrite($SpoolFileHandle, $data)) {
				// close $SpoolFileHandle
				fclose($SpoolFileHandle);

				// chmod to group writable so that the perl scripts, running
				// as dan, can remove them when a new commit comes in.
				// the leading zero is important.
				chmod($SpoolFileName, 0664);

				// mv spool file to cache dir
				$CacheFileName = $this->_CacheFileName($key);

				// mv $SpoolFileName $CacheFileName
				if (rename($SpoolFileName, $CacheFileName)) {
					// success
					$this->_Log('Cache: Add ' . $CacheFileName);
				} else {
					// rm $SpoolFileName
					unlink($SpoolFileName);
					$this->_Log('Cache: FAILED Add on move" ' . $SpoolFileName . ' to ' . $CacheFileName);
				}
			} else {
				fclose($SpoolFileHandle);
				$this->_Log('Cache: FAILED Add on write: ' . $SpoolFileName);
			}
		} else {
			$this->_Log('Cache: FAILED Add on opening file: ' . $SpoolFileName);
			$result = -1;
		}

		return $result;
	}

	function Remove($key) {
		$result = 0;

		$FileName = $this->_CacheFilename($key);
		// rm $Filename
		if (unlink($Filename)) {
			// success
			$this->_Log('Cache: Remove ' .$CacheFileName);
		} else {
			$this->_Log('Cache: FAILED Remove ' .$CacheFileName);
			$result = -1;
		}
		
		return $result;
	}

	function _CleanKey($key) {
		// convert /../ to .
		
		$new_key = $key;
		$new_key = str_replace('/../', '', $new_key);
		$new_key = str_replace('../',  '', $new_key);
		$new_key = str_replace('/..',  '', $new_key);
		$new_key = str_replace('..',   '', $new_key);

		$new_key = str_replace('/',   '.', $new_key);

		return $new_key;
	}

	function _SpoolFileName($key) {
		$FileName = tempnam($this->SpoolDir, $this->_CleanKey($key) . '.tmp');

		this->_Log('Cache: creating spool file ' . $FileName);

		return $FileName;
	}

	function _CacheFileName($key) {
		$FileName = $this->CacheDir . '/'. $this->_CleanKey($key);

		return $FileName;
	}
	
	function _Log($activity) {
		// log the above message
		syslog(LOG_NOTICE, $activity);
	}
}

?>