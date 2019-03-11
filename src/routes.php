<?php

/*
 *  Special routes
 */

$app->get('/sitemap', function ($request, $response, $args) {
    return $response->withStatus(200)
        ->withHeader('Content-Type', 'text/xml')
        ->write(print_sitemap());
});

//dateclick
$app->post('/api/get_events_by_date', function ($request, $response, $args) {
    $post = (object)$request->getParams();
    $player_name = norm($post->player_name);
    $date = $post->date;
    $player_id = get_player_id($player_name);
    $logs = get_player_logs_by_date($player_id, $date);
    echo json_encode($logs);
});

/*
 *  Basic routes
 */

$app->get('/filter', function ($request, $response, $args) {
    return $this->view->fetch('filter.twig', $args);
});

$app->get('/about', function ($request, $response, $args) {
    $args['stats'] = get_statistics();
    return $this->view->fetch('about.twig', $args);
});

$app->get('/test', function ($request, $response, $args) {
    //oof ouch owie my debug function
    var_dump(is_capped(101));
});

/*
 *  Clans
 */

$app->map(['GET','POST'], '/clan/[{clan}]', function ($request, $response, $args) {

    if (isset($args['clan'])) {

        $clan_name = norm($args['clan']);
        $clan_members = get_clan_members($clan_name);

        foreach($clan_members as $member) {
            $cap_check = is_capped($member->us_id);

            if($cap_check){
                $args['capped'][] = $member->us_name;
            } else {
                $args['not_capped'][] = $member->us_name;
            }
        }
    }

    return $this->view->fetch('clan.twig', $args);
});
/*
 *  Profile (index, logs & search)
 */

$app->map(['GET','POST'], '/[{player}]', function ($request, $response, $args) {

    if (isset($args['player'])) {

        //validate the player name
        $player_name = norm($args['player']);
        $player_id = get_player_id($player_name);

        if ($player_id) { //if the name is recognized in our database

            //set the cookie for last visited profile
            setcookie('player',$player_name ,time()+3600*24*365,'/','runelo.gs');

            //check if post (search)
            if ($request->isPost()) {

                $post = (object)$request->getParams();
                
                //retrieve the search term.
                $args['search'] = $post->search;
                $args['results'] = search($player_id, $args['search']);

            } else {

                $all_player_logs = get_all_player_logs($player_id);
                $args['grid'] = generate_player_grid($all_player_logs);
                $player_logs = get_player_logs_by_date($player_id, time());

                if ($player_logs) {
                    $args['logs'] = $player_logs;
                } else {
                    //set message = no logs for that day
                    $args['type'] = "inf";
                    $args['message'] = "Looks like you don't have any logs for today.";
                }
            }

        } else {

            $r = new \Ralph\api();
            $profile_check = $r->get_profile($player_name);
            if (isset($profile_check->error)) {
                $args['type'] = "err";
                $args['message'] = "That is not an active Runescape account with a public Adventure Log.";
            } else {
                if (!check_user($player_name)) {
                    $player_clan = get_player_clan($player_name);
                    add_user($player_name, $player_clan);
                    $args['type'] = "suc";
                    $args['message'] = "We've added your account to our database. Wait for the next update cycle to hit for your logs to show up.";
                } else {
                    $args['type'] = "inf";
                    $args['message'] = "We already have that account in our database. Wait for the next update cycle to hit for your logs to show up.";
                }
            }
        }

        return $this->view->fetch('profile.twig', $args);

    } else {

        if ($request->isPost()) {
            $post = (object)$request->getParams();
            if (isset($post->player)) {
                $player_name = norm($post->player);
                return $response->withRedirect('/'.$player_name);
            }
        }

        $args['latest'] = get_newest_logs();
        /*
        if (!empty($_COOKIE['player'])) {   
            $args['player'] = $_COOKIE['player'];
        }
        */
        $args['headliners'] = ["drop", "pet", "level", "milestone"];
        

        return $this->view->fetch('index.twig', $args);
    }
});