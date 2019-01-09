<?php
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
	$result = $db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
	$db = null;
	$output = [];
	foreach($result as $key => $name){
		$trim_name = str_replace(' ', '+', htmlentities(utf8_encode(strtolower($name))));
		$output[$trim_name] = $key;
	}
	return $output;
}

function get_user($user_id)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM users WHERE us_id = :user_id");
	$stmt->bindParam(':user_id', $user_id);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $result[0];
	$db = null;
}

function check_user($player_name)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM users WHERE us_name = :player_name");
	$stmt->bindParam(':player_name', $player_name);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(isset($result[0])){
		return true;
	}else{
		return false;
	}
	$db = null;
}

function add_user($player_name)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("INSERT INTO users VALUES (null, :player_name)");
	$stmt->bindParam(':player_name', $player_name);

	$stmt->execute();
	return $db->lastInsertId(); //user id just created
	$db = null;
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
	return $result;
	$db = null;
}

function get_players_last_log($player_id)
{
	$db = new PDO("sqlite:".__DIR__ ."/../data/db.sqlite");
	$stmt = $db->prepare("SELECT * FROM logs INNER JOIN users ON users.us_id = logs.lg_us_id WHERE users.us_id = :player_id ORDER BY logs.lg_ts DESC LIMIT 1");
	$stmt->bindParam(':player_id', $player_id);
	$stmt->execute();

	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(isset($result[0])){
		return (object)$result[0];
	}else{
		return false;
	}
	$db = null;
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
	return $result;
	$db = null;
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

/* Run-eMetrics stuff */

function update_logs()
{
	$r = new \Ralph\api();
	$users = get_users(); //get a name indexed list of all the users in our database
	$runemetrics_objects = $r->get_bulk_profiles($users); //get the runemetrics profiles of all these users
	$filtered_logs = [];

	foreach($runemetrics_objects as $runemetric_object){

		$player_id = $runemetric_object->id; //get the player id from the runemetrics object
		echo 'Checking user '.$player_id.': ';
		$player_last_log = get_players_last_log($player_id); //get the last log for that player from our database
		$player_activities = $runemetric_object->activities; //the last 20 activites from the players' runemetrics object

		if (!$player_last_log) { //no last log, save all

	        foreach($player_activities as $activity){
				$activity->user_id = intval($player_id); //add user id to each log, make sure it's an int
				$activity->timestamp = strtotime($activity->date); //convert the jagex' date into an epoch timestamp and get rid of the date afterwards
				unset($activity->date);
				//if they pass the filter, add them to the filtered array
				if(filter_log($activity->text)){
					$filtered_logs[] = $activity;
				}
			}
	    } else {

	        $last_log_found = false;

	        foreach (array_reverse($player_activities) as $activity_index => $activity) { //array_reverse so you start the loop at the bottom, with the oldest log
	            
	            if ($last_log_found == true) {
	                
	                $activity->user_id = intval($player_id); //add user id to each log, make sure it's an int
					$activity->timestamp = strtotime($activity->date); //convert the jagex' date into an epoch timestamp and get rid of the date afterwards
					unset($activity->date);
					//if they pass the filter, add them to the filtered array
					if(filter_log($activity->text)){
						$filtered_logs[] = $activity;
					}
	            }else{
	                if (match_logs($player_last_log, $activity)) {
	                    $last_log_found = true;
	                }
	            }
	        }
	        //looped over all the logs and couldnt find the last log -> add all new logs
	        if ($last_log_found == false) {

	            foreach($player_activities as $activity){
					//add user id to each log
					$activity->user_id = intval($player_id);
					$activity->timestamp = strtotime($activity->date);
					unset($activity->date);
					//if they pass the filter, add them to the filtered array
					if(filter_log($activity->text)){
						$filtered_logs[] = $activity;
					}
				}
	        }
	    }
	    echo "adding ".count($filtered_logs)." new logs."."\r\n";
	}
	
	//and like that.. he's gone
	add_logs($filtered_logs);
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
    if ($db_log->lg_title == $rm_log->text && $db_log->lg_ts == strtotime($rm_log->date)) {
        return true;
    } else {
        return false;
    }
}

/* Helpers */

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