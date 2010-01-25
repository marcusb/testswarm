<?php

	function get_status($num){
		if ( $num == 0 ) {
			return "Not started yet.";
		} else if ( $num == 1 ) {
			return "In progress.";
		} else {
			return "Completed.";
		}
	}

	function get_status2($num, $fail, $error, $total){
		if ( $num == 0 ) {
			return "notstarted notdone";
		} else if ( $num == 1 ) {
			return "progress notdone";
		} else if ( $num == 2 && $fail == -1 ) {
			return "timeout";
		} else if ( $num == 2 && ($error > 0 || $total == 0) ) {
			return "error";
		} else {
			return $fail > 0 ? "fail" : "pass";
		}
	}

	$result = mysql_query("SELECT useragents.engine as engine, useragents.name as name, useragents.os as os, DATE_FORMAT(clients.created, '%Y-%m-%dT%H:%i:%sZ') as since FROM users, clients, useragents WHERE clients.useragent_id=useragents.id AND DATE_ADD(clients.updated, INTERVAL 1 minute) > NOW() AND clients.user_id=users.id AND users.name='$search_user';");

	if ( mysql_num_rows($result) > 0 ) {

	echo "<h3>Active Clients:</h3><ul class='clients'>";

	while ( $row = mysql_fetch_array($result) ) {
		$engine = $row[0];
		$browser_name = $row[1];
		$name = $row[2];
		$since = $row[3];

		if ( $name == "xp" ) {
			$name = "Windows XP";
		} else if ( $name == "vista" ) {
			$name = "Windows Vista";
		} else if ( $name == "win7" ) {
			$name = "Windows 7";
		} else if ( $name == "2000" ) {
			$name = "Windows 2000";
		} else if ( $name == "2003" ) {
			$name = "Windows 2003";
		} else if ( $name == "osx10.4" ) {
			$name = "OS X 10.4";
		} else if ( $name == "osx10.5" ) {
			$name = "OS X 10.5";
		} else if ( $name == "osx10.6" ) {
			$name = "OS X 10.6";
		} else if ( $name == "osx" ) {
			$name = "OS X";
		} else if ( $name == "linux" ) {
			$name = "Linux";
		}

		echo "<li><img src='" . $GLOBALS['contextpath'] . "/images/$engine.sm.png' class='$engine'/> <strong class='name'>$browser_name $name</strong><br>Connected <span title='$since' class='pretty'>$since</span></li>";
	}

	echo "</ul>";

	}

	$job_search = preg_replace("/[^a-zA-Z ]/", "", $_REQUEST['job']);
	$job_search .= "%";

	$browser_res = mysql_queryf("SELECT ua.id, ua.name, ua.engine, ua.os as os FROM jobs join users on jobs.user_id join runs on runs.job_id=jobs.id join run_useragent on run_useragent.run_id=runs.id join useragents ua on run_useragent.useragent_id=ua.id WHERE jobs.name LIKE %s AND users.name=%s AND jobs.user_id=users.id group by id order by name, os", $job_search, $search_user);
	$browsers = array();
	while ($b = mysql_fetch_assoc($browser_res)) {
	      array_push($browsers, $b);
	}

	$output = "<tr><th></th>\n";
	foreach ( $browsers as $browser ) {
		$output .= '<th><div class="browser">' .
			'<img src="' . $GLOBALS['contextpath'] . '/images/' . $browser["engine"] .
			'.sm.png" class="browser-icon ' . $browser["engine"] .
			'" alt="' . $browser["name"] . ', ' . $browser["os"] .
			'" title="' . $browser["name"] . ', ' . $browser["os"] .
			'"/><span class="browser-name">' .
			preg_replace('/\w+ /', "", $browser["name"]) . ', ' .
			$browser["os"] . '</span></div></th>';
	}
	$output .= "</tr>\n";

	$search_result = mysql_queryf("SELECT jobs.name, jobs.status, jobs.id FROM jobs, users WHERE jobs.name LIKE %s AND users.name=%s AND jobs.user_id=users.id ORDER BY jobs.created DESC LIMIT 15;", $job_search, $search_user);

	if ( mysql_num_rows($search_result) > 0 ) {

	echo "<br/><h3>Recent Jobs:</h3><table class='results'><tbody>";

	while ( $row = mysql_fetch_array($search_result) ) {
		$job_name = $row[0];
		$job_status = get_status(intval($row[1]));
		$job_id = $row[2];

		$output .= '<tr><th><a href="' . $GLOBALS['contextpath'] . '/job/' . $job_id . '/">' . strip_tags($job_name) . "</a></th>\n";

		$states = array();

	$result = mysql_queryf("SELECT runs.id as run_id, clients.useragent_id as useragent_id, run_client.status as status, run_client.fail as fail, run_client.error as error, run_client.total as total FROM runs, run_client, clients WHERE runs.job_id=%u AND run_client.run_id=runs.id AND clients.id=run_client.client_id ORDER BY run_id;", $job_id);

	$useragents = array();
	while ( $ua_row = mysql_fetch_assoc($result) ) {
		if ( !$useragents[ $ua_row['useragent_id'] ] ) {
			$useragents[ $ua_row['useragent_id'] ] = array();
		}
		array_push( $useragents[ $ua_row['useragent_id'] ], $ua_row );
	}

	foreach ($browsers as $browser) {
		$bid = $browser['id'];
		if ($useragents[$bid]) {
			foreach ($useragents[$bid] as $ua) {
				$status = get_status2(intval($ua["status"]), intval($ua["fail"]), intval($ua["error"]), intval($ua["total"]));
				$cur = $states[$bid];
				if ( strstr($status, "notdone") || strstr($cur, "notdone") ) {
					$status = "notstarted notdone";
				} else if ( $status == "error" || $cur == "error" ) {
					$status = "error";
				} else if ( $status == "timeout" || $cur == "timeout" ) {
					$status = "timeout";
				} else if ( $status == "fail" || $cur == "fail" ) {
					$status = "fail";
				} else {
					$status = "pass";
				}

				$states[$bid] = $status;
			}
		} else {
error_log("no browser " . $bid);
			$states[$bid] = "notstarted notdone";
		}
	}

	foreach ($browsers as $b) {
		$output .= "<td class='" . $states[$b['id']] . "'></td>";
	}

	$output .= "</tr>\n";

	}

	echo "$output</tr>\n</tbody>\n</table>";

	}
?>
