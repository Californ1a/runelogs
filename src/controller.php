<?php
date_default_timezone_set('UTC'); //fuck jagex and fuck php
require __DIR__ .  '/ralph.php';

/*
 *  Runelo.gs controller
 *
 *  Legend:
 *      - DB functions (1 to 140)
 *      - Log update (140 to 255)
 *      - Helpers (255 to 280)
 *
 *  Yes everything is in 1 controller, it's only 280 lines you doofus take ur oop mvc elsewhere
 */

/*
 *  DB stuff
 *  ? DELETE FROM logs WHERE rowid NOT IN (SELECT min(rowid) FROM logs GROUP BY logs.lg_ts, logs.lg_details);
 */

function get_users() : array
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $sql = "SELECT * FROM users";
    $result = $db->query($sql)->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    return $result;
}

function get_user(int $user_id)
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM users WHERE us_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_OBJ);
    $db = null;
    return $result;
}

function check_user(string $player_name) : bool
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM users WHERE us_name = :player_name");
    $stmt->bindParam(':player_name', $player_name);
    $stmt->execute();

    $result = $stmt->fetch();
    $db = null;
    return $result ? true : false; 
}

function get_clan_members(string $clan_name)
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM users WHERE us_clan = :clan_name");
    $stmt->bindParam(':clan_name', $clan_name);
    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    return $result; 
}

function add_user(string $player_name, string $player_clan) : int
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("INSERT INTO users VALUES (null, :player_name, :player_clan)");
    $stmt->bindParam(':player_name', $player_name);
    $stmt->bindParam(':player_clan', $player_clan);

    $stmt->execute();
    $last_id = $db->lastInsertId();
    $db = null;
    return $last_id;
}

function get_player_id(string $player_name) : int
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT us_id FROM users WHERE us_name = :player_name");
    $stmt->bindParam(':player_name', $player_name);
    $stmt->execute();

    $result = intval($stmt->fetchColumn());
    $db = null;
    return $result;
}

function get_player_logs_by_date(int $player_id, int $date) : array
{
    $start = strtotime("midnight", $date);
    $end = strtotime("tomorrow", $start) - 1;
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM logs WHERE lg_us_id = :player_id AND (lg_ts >= :start AND lg_ts <= :end) ORDER BY lg_ts DESC");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':start', $start);
    $stmt->bindParam(':end', $end);
    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    return $result;
}

function get_all_player_logs(int $player_id) : array
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM logs WHERE lg_us_id = :player_id ORDER BY lg_ts");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    return $result;
}

function get_players_last_log(int $player_id)
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM logs WHERE lg_us_id = :player_id ORDER BY logs.lg_id DESC LIMIT 1");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_OBJ);
    $db = null;
    if($result){
        return $result;
    }else{
        return false;
    }
}

function search(int $player_id, string $search_term) : array
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $search_term = '%'.$search_term.'%'; //prep the search query here cus sqlite doesnt like it when u do this inline
    $stmt = $db->prepare("SELECT * FROM logs WHERE lg_us_id = :player_id AND (lg_title LIKE :search_term OR lg_details LIKE :search_term) ORDER BY lg_ts DESC");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':search_term', $search_term);
    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    return $result;
}

function add_logs(array $logs_to_add)
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
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

function get_statistics() : object
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $sql = "SELECT count(*) as user_count FROM users;";
    $user_count = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    $sql = "SELECT count(*) log_count FROM logs;";
    $log_count = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    $statistics = (object) array_merge($user_count, $log_count); //get off my back
    $db = null;
    return $statistics;
}

function get_newest_logs()
{
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $sql = "SELECT * FROM logs INNER JOIN users ON users.us_id = logs.lg_us_id GROUP BY users.us_name ORDER BY logs.lg_ts DESC LIMIT 250;";
    $result = $db->query($sql)->fetchAll(PDO::FETCH_OBJ);

    $db = null;
    return $result;
}

function is_capped(int $player_id) : bool
{
    $reset_date = date('l ga', strtotime("Wednesday 16:00"));
    $last_reset = strtotime('last '.$reset_date);
    $db = new PDO('sqlite:'.__DIR__ .'/../data/db.sqlite');
    $stmt = $db->prepare("SELECT * FROM logs WHERE lg_us_id = :player_id AND lg_details = 'I have capped at my Clan Citadel this week.' AND lg_ts >= :last_reset");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':last_reset', $last_reset);
    $stmt->execute();

    $result = $stmt->fetch();
    $db = null;
    return $result ? true : false; 
}

/*
 *  Run-eMetrics section
 */

function update_logs()
{   
    $r = new \Ralph\api();
    $users = get_users();
    $player_profiles = $r->get_bulk_profiles($users);
    $list_of_logs_to_add = [];

    foreach ($player_profiles as $player_profile) {
        
        echo '#'.$player_profile->id.' '.$player_profile->name.' | '; //debug output

        if (isset($player_profile->error)) {
            
            echo 'Profile error: '.$player_profile->error."\r\n"; //if no one is found, mark that player as broken

        } else {

            //filter it
            $filtered_list = [];
            foreach ($player_profile->activities as &$activity) {
                $activity->user_id = intval($player_profile->id);
                $activity->timestamp = strtotime($activity->date);
                unset($activity->date);
                if (filter_log($activity->text)) {
                    $filtered_list[] = $activity;
                }
            }

            //and continue with the filtered list
            if (empty($filtered_list)) {

                echo 'no new logs found'."\r\n";

            } else {

                $player_last_log = get_players_last_log($player_profile->id); //get the last log for that player from our database

                if (!$player_last_log) { //no last log, save all

                    echo 'no link, saving all'."\r\n";
                    $list_of_logs_to_add = array_merge($list_of_logs_to_add, array_reverse($filtered_list));

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

                    if ($last_log_found == false) {

                        echo 'link not found, saving all'."\r\n";
                        $list_of_logs_to_add = array_merge($list_of_logs_to_add, array_reverse($filtered_list));
                    }
                }
            }
        }
    }

    add_logs($list_of_logs_to_add);
}

/*
 *  Log filtration
 *  $t is the log text, $f is the filter array
 *  Explode the log text, then array intersect with the filter array, return bool
 */
function filter_log(string $t): bool
{
    $f = ['trisk', 'effigy', 'battle', 'whip', 'dark', 'Forcae', 'dragon'];
    return array_intersect($f, explode(' ', strtolower($t))) ? false : true;
}

/*
 *  Log matching
 *  Compare our link (the last local log) and a newer log
 */
function match_logs(object $link, object $rm_log) : bool
{   
    return $link->lg_title == $rm_log->text && $link->lg_ts == $rm_log->timestamp ? true : false;
}

function get_player_clan(string $player_name)
{
    $r = new \Ralph\api();
    if (isset($r->get_details($player_name)->clan)) {
        return norm($r->get_details($player_name)->clan);
    } else {
        return 'none';
    }
}

function generate_player_grid(array $player_logs)
{
    $logs_sorted_by_day = [];

    foreach ($player_logs as $log) {   
        $logs_sorted_by_day[date('z', $log->lg_ts)][] = $log;
    }
    ob_start();

    for ($i=0; $i <= 365; $i++) {

        $dayint = date('z', time());
        $today_class = ($dayint == $i) ? 'today' : '';

        if(isset($logs_sorted_by_day[$i])){
            $logs_on_that_day = $logs_sorted_by_day[$i];

            $quantity = count($logs_sorted_by_day[$i]);
            if ($quantity <= 5) {
                $activity = 'low';
            } else if ($quantity <= 10) {
                $activity = 'medium';
            } else if ($quantity <= 20) {
                $activity = 'high';
            } else if ($quantity > 20) {
                $activity = 'sweat';
            }
            
            $day_timestamp = $logs_on_that_day[0]->lg_ts;
            $day = date('M j, Y', $day_timestamp);

            echo '<span class="grid-square '.$activity .' '. $today_class . '" data-date="'.$day_timestamp.'" data-balloon="'.$quantity.' logs on '.$day.'" data-balloon-pos="down"></span>';

        } else {
            echo '<span class="grid-square '. $today_class . '"></span>';
        }
    }

    $grid = ob_get_clean();
    return $grid;
}

/*
 *  Helpers
 */

function norm(string $string) : string
{
    $to_replace = [" ", "_"];
    return str_replace($to_replace, '+', htmlentities(utf8_encode(strtolower($string))));
}

function print_sitemap()
{
    $base_url = 'https://runelo.gs';
    $urls = ['/','/about','/filter'];

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