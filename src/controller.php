<?php
date_default_timezone_set('UTC'); //fuck jagex and fuck php
require __DIR__ .  '/ralph.php';

/* DB stuff */
/*
 *
 *	Note to self: if you get duplicates in your db because your code is garbage, flsuh em out with this query:
 *  	DELETE FROM logs WHERE rowid NOT IN (SELECT min(rowid) FROM logs GROUP BY logs.lg_ts, logs.lg_details);
 *
 */

function get_users()
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$sql = "SELECT * FROM users";
	$result = $db->query($sql)->fetchAll(PDO::FETCH_OBJ);
	$db = null;
	return $result;
}

function get_user($user_id)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM users WHERE us_id = :user_id");
	$stmt->bindParam(':user_id', $user_id);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$db = null;
	return $result[0];
}

function check_user($player_name)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM users WHERE us_name = :player_name");
	$stmt->bindParam(':player_name', $player_name);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$db = null;
	if(isset($result[0])){
		return true;
	}else{
		return false;
	}
}

function add_user($player_name, $player_clan)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("INSERT INTO users VALUES (null, :player_name, :player_clan)");
	$stmt->bindParam(':player_name', $player_name);
	$stmt->bindParam(':player_clan', $player_clan);

	$stmt->execute();
	$last_id = $db->lastInsertId();
	$db = null;
	return $last_id; //user id just created
}


function get_player_logs($player_name, $page)
{
	$limit = 20;
	$offset = ($page - 1)  * $limit;
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM logs INNER JOIN users ON users.us_id = logs.lg_us_id WHERE users.us_name = :player_name ORDER BY logs.lg_ts DESC LIMIT :limit OFFSET :offset");
	$stmt->bindParam(':player_name', $player_name);
	$stmt->bindParam(':limit', $limit);
	$stmt->bindParam(':offset', $offset);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$db = null;
	return $result;
}

function get_players_last_log($player_id)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM logs INNER JOIN users ON users.us_id = logs.lg_us_id WHERE users.us_id = :player_id ORDER BY logs.lg_id DESC LIMIT 1");
	$stmt->bindParam(':player_id', $player_id);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_OBJ);
	$db = null;
	if(isset($result[0])){
		return $result[0];
	}else{
		return false;
	}
}

function search($player_name, $search_term)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$search_term = '%'.$search_term.'%';
	$stmt = $db->prepare("SELECT * FROM logs INNER JOIN users ON users.us_id = logs.lg_us_id WHERE users.us_name = :player_name AND (logs.lg_title LIKE :search_term OR logs.lg_details LIKE :search_term) ORDER BY logs.lg_ts DESC");
	$stmt->bindParam(':player_name', $player_name);
	$stmt->bindParam(':search_term', $search_term);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$db = null;
	return $result;
}

function add_logs($logs_to_add)
{
    $db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO logs VALUES (null, :user_id, :log_title, :log_details, :log_timestamp)");
    foreach ($logs_to_add as $log) {
        $stmt->bindParam(':user_id', $log->user_id);
		$stmt->bindParam(':log_title', $log->text);
		$stmt->bindParam(':log_details', $log->details);
		$stmt->bindParam(':log_timestamp', $log->timestamp);
        $stmt->execute();
    }

    $db->commit();
    $db = null;
}

function get_statistics()
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$sql = "SELECT count(*) as user_count FROM users;";
	$result['user_count'] = $db->query($sql)->fetchAll(PDO::FETCH_OBJ)[0]->user_count;
	$sql = "SELECT count(*) log_count FROM logs;";
	$result['log_count'] = $db->query($sql)->fetchAll(PDO::FETCH_OBJ)[0]->log_count;
	$db = null;
	return (object)$result;
}

/* 
 * Run-eMetrics stuff
 *		the real big boi function
 *	will document 
 */

function update_logs()
{	
	/*
	 *	Declare a new instance of the Ralph API we use to fetch Runemetric data
	 *
	 */

	$r = new \Ralph\api();

	/*
	 *	Fetch a list of all our users, and grab the Runemetrics profile for each user
	 *
	 */

	$users = get_users();
	$player_profiles = $r->get_bulk_profiles($users);

	/*
	 *	Prepare an empty array to hold all the logs we will add to the db (in one transaction)
	 *
	 */

	$list_of_logs_to_add = [];

	/*
	 *	Start looping all of the Runemetric profiles
	 *
	 */

	foreach($player_profiles as $player_profile){
		
		echo '#'.$player_profile->id.' '.$player_profile->name.' | '; //debug output

		/*
		 *	Check for RM errors, possible ones are NO_PROFILE or PROFILE_PRIVATE
		 *
		 */
		if (isset($player_profile->error)) {
			//try looking for name changes

			//get clan name from user record

			//if no one is found, mark that player as broken
			echo 'Profile error: '.$player_profile->error."\r\n";

		} else {
			
			$player_activities = $player_profile->activities; //the last 20 activites from the players' runemetrics object

			/*
			 *	Filter all player activities on keywords
			 *
			 */

			$filtered_list = [];

			foreach ($player_activities as &$activity) {
				$activity->user_id = intval($player_profile->id);
				$activity->timestamp = strtotime($activity->date);
				unset($activity->date);
				if(filter_log($activity->text)){
					$filtered_list[] = $activity;
				}
			}

			/*
			 *	Proceed with the linking process
			 *	During the linking process, we will use the last log found in the db (the link) to
			 *
			 */

			if (empty($filtered_list)) {

				echo 'no new logs found'."\r\n";

			} else {

				$player_last_log = get_players_last_log($player_profile->id); //get the last log for that player from our database

				if (!$player_last_log) { //no last log, save all

					echo 'no link, saving all'."\r\n";
			        $list_of_logs_to_add = array_merge($list_of_logs_to_add, $filtered_list);

			    } else {

			        $last_log_found = false;

			        foreach (array_reverse($filtered_list) as $act_index => $activity) { //array_reverse so you start the loop at the bottom, with the oldest log

			            if ($last_log_found == true) {
			                
			            	$list_of_logs_to_add[] = $activity;
							
			            }else{
			                if (match_logs($player_last_log, $activity)) {
			                	if ($act_index == (count($filtered_list) - 1)) {
			                		echo 'link is up to date'."\r\n";
			                	} else {
			                		echo 'link found at index '.$act_index.'/'.count($filtered_list).', saving '.(count($filtered_list) - $act_index).' newer ones'."\r\n";
			                	}
			                    $last_log_found = true;
			                }
			            }
			        }

			        /*
					 *	Looped over all the logs and couldnt find the link -> add all new logs
					 *
					 */

			        if ($last_log_found == false) {

			        	echo 'link not found, saving all'."\r\n";
			        	$list_of_logs_to_add = array_merge($list_of_logs_to_add, $filtered_list);
			        }
			    }

			}
    
		}
		
	}

	add_logs($list_of_logs_to_add);
}

function filter_log($log_text)
{
    $ignore = ['trisk', 'effigy', 'battle', 'whip', 'dark', 'Forcae', 'dragon'];
    
    $exploded = explode(' ', strtolower($log_text));
    
    if(!array_pintersect($ignore, $exploded))
    {
        return true;
    }
    
    return false;
}

function match_logs($db_log, $rm_log) //first one is from db, second one is from RM
{   
    if ($db_log->lg_title == $rm_log->text && $db_log->lg_ts == $rm_log->timestamp) {
        return true;
    } else {
        return false;
    }
}

function get_player_clan($player_name)
{
    $r = new \Ralph\api();
    if (isset($r->get_details($player_name)->clan)) {
    	return $r->get_details($player_name)->clan;
    } else {
    	return null;
    }
}

/* Helpers */

function norm($string)
{
	return str_replace(' ', '+', htmlentities(utf8_encode(strtolower($string))));
}

function array_pintersect(array $needles, array $haystack)
{
    foreach ($haystack as $hay) {
        foreach ($needles as $needle) {
            $hay = strval($hay);
            $pos = stripos($hay, $needle);
            if ($pos !== false) {
                return true;
            }
        }
    }
    return false;
}

function print_sitemap()
{
	$base_url = 'https://runelo.gs';
	$urls = ["/","/about","/filter"];

	echo '<?xml version="1.0" encoding="utf-8"?>'.PHP_EOL.
	'<urlset xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

	foreach ($urls as $url) {
	    echo '<url>' . PHP_EOL;
	    echo '<loc>'.$base_url.$url.'</loc>' . PHP_EOL;
	    echo '<changefreq>daily</changefreq>' . PHP_EOL;
	    echo '</url>' . PHP_EOL;
	}

	echo '</urlset>' . PHP_EOL;
}