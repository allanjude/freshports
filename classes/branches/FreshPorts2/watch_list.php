<?
	# $Id: watch_list.php,v 1.1.2.7 2003-01-04 17:10:45 dan Exp $
	#
	# Copyright (c) 1998-2001 DVL Software Limited
	#

$Debug = 0;

// base class for a single watchlist
class WatchList {

	var $dbh;

	var $id;
	var $user_id;
	var $name;
	var $in_service;
	var $watch_list_count;
	
	var $LocalResult;


	function WatchList($dbh) {
		$this->dbh	= $dbh;
	}
	
	function Create($UserID, $Name) {
		#
		# create a new and empty watch list
		#
		GLOBAL $Sequence_Watch_List_ID;

		$return = 0;

		AddSlashes($Name);
		
		$query = "
SELECT count(watch_list.id), users.max_number_watch_lists
    FROM users LEFT OUTER JOIN watch_list
               ON users.id = watch_list.user_id
   WHERE users.id = $UserID
GROUP BY users.max_number_watch_lists";

		$this->LocalResult = pg_query($this->dbh, $query);
		if ($this->LocalResult) {
			$numrows = pg_numrows($this->LocalResult);
			if ($numrows == 1) {
				$myrow = pg_fetch_array($this->LocalResult, 0);
				$Count = $myrow[0];
				$Max   = $myrow[1];
				if ($Count < $Max) {
					$NextValue = freshports_GetNextValue($Sequence_Watch_List_ID, $this->dbh);
			
					$query  = "insert into watch_list (id, user_id, name) values ($NextValue, $UserID, '$Name')";
					$result = pg_query($this->dbh, $query);
			
					# that worked and we updated exactly one row
					if ($result && pg_affected_rows($result) == 1) {
						$return = $NextValue;
					}
				} else {
					syslog(LOG_NOTICE, "You already have $Count watch lists.  If you want more than $Max watch lists, please contact the postmaster. UserID='$UserID'");
					die("You already have $Count watch lists.  If you want more than $Max watch lists, please contact the postmaster.");
				}
			} else {
				syslog(LOG_ERR, "Could not find watch list count for user $UserID - " . $_SERVER['PHP_SELF']);
				die("I couldn't find your watch list details... sorry");
			}
		} else {
			syslog(LOG_ERR, "Error finding watch list count for user $UserID - " . $_SERVER['PHP_SELF'] . ' ' . pg_last_error());
			die('Error finding watch list count for user');
		}

		return $return;
		
	}

	function Delete($UserID, $WatchListID) {
		#
		# Delete a watch list
		#
		unset($return);

		$query  = '
DELETE FROM watch_list 
 WHERE id = ' . AddSlashes($WatchListID) .'
   AND user_id = ' . $UserID;

		if ($Debug) echo $query;
		$result = pg_query($this->dbh, $query);

		# that worked and we updated exactly one row
		if ($result && pg_affected_rows($result) == 1) {
			$return = $WatchListID;
		}

		return $return;
	}

	function EmptyTheList($UserID, $WatchListID) {
		#
		# Empty a watch list (couldn't use empty, as that's reserved)
		#
		unset($return);
		$Debug = 0;

		$query = "
DELETE FROM watch_list_element
 WHERE watch_list.id                    = $WatchListID
   AND watch_list.user_id               = $UserID
   AND watch_list_element.watch_list_id = watch_list.id";

		if ($Debug) echo $query;
		$result = pg_query($this->dbh, $query);

		# that worked and we updated exactly one row
		if ($result) {
			$return = $WatchListID;
		}

		return $return;
	}

	function Rename($UserID, $WatchListID, $NewName) {
		#
		# Delete a watch list
		#
		unset($return);

		$query  = '
UPDATE watch_list 
   SET name = \'' . AddSlashes($NewName) . '\' 
 WHERE id = ' . AddSlashes($WatchListID) . '
   AND watch_list.user_id = ' . $UserID;
		if ($Debug) echo $query;
		$result = pg_query($this->dbh, $query);

		# that worked and we updated exactly one row
		if ($result && pg_affected_rows($result) == 1) {
			$return = $NewName;
		}

		return $return;
	}

	
	function Fetch($UserID, $ID) {
		$sql = "
		SELECT id,
		       user_id,
		       name,
		       in_service
		  FROM watch_list
		 WHERE id      = $ID
		   AND user_id = $UserID";

#		echo '<pre>' . $sql . '</pre>';

		if ($Debug)	echo "WatchLists::Fetch sql = '$sql'<BR>";

		$this->LocalResult = pg_exec($this->dbh, $sql);
		if ($this->LocalResult) {
			$numrows = pg_numrows($this->LocalResult);
#			echo "That would give us $numrows rows";
		} else {
			$numrows = -1;
			echo 'pg_exec failed: ' . $sql;
		}

		return $numrows;
	}


	function PopulateValues($myrow) {
		#
		# call Fetch first.
		# then call this function N times, where N is the number
		# returned by Fetch.
		#

		$this->id					= $myrow["id"];
		$this->user_id				= $myrow["user_id"];
		$this->name					= $myrow["name"];
		$this->in_service			= $myrow["in_service"];
		$this->watch_list_count = $myrow["watch_list_count"];
	}
}
