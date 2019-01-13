<?php
// Routes
$app->get('/filter', function ($request, $response, $args) {
    return $this->view->fetch('filter.twig', $args);
});

$app->get('/about', function ($request, $response, $args) {
    $args['stats'] = get_statistics();
    return $this->view->fetch('about.twig', $args);
});

$app->get('/toggle-nightmode', function ($request, $response, $args) {

    if (!isset($_SESSION['nightmode'])) {
        $_SESSION['nightmode'] = true;
    } else {
        $_SESSION['nightmode'] = !$_SESSION['nightmode'];
    }
    return $_SESSION['nightmode'];
});

//infinite scroll bullshit
$app->get('/load/{player}/{page}', function ($request, $response, $args) {
	$player_name = strtolower($args['player']);
	$page = intval($args['page']);
	$logs = get_player_logs($player_name, $page);
    echo json_encode($logs);
});

//the big boi

$app->map(['GET','POST'], '/[{player}]', function ($request, $response, $args) {

    if(isset($args['player'])){

        //validate the player name
        $player_name = $args['player'];

        //check if post (search)
        if ($request->isPost()) {

            $post = (object)$request->getParams();
            
            //retrieve the search term.
            $args['search'] = $post->search;
            $args['results'] = search($player_name, $args['search']);

        } else {

            $player_logs = get_player_logs($player_name, 1);
            if($player_logs){
                $args['logs'] = $player_logs;
            }else{
                $r = new \Ralph\api();
                $profile_check = $r->get_profile($player_name);
                if(isset($profile_check->error)){
                    $args['message'] = "That's not an active Runescape account with a public Adventure Log.";
                } else {
                    if(!check_user($player_name)){
                        add_user($player_name);
                        $args['message'] = "We've added your account to our database. Wait for the next update cycle to hit for your logs to show up.";
                    } else {
                        $args['message'] = "We already have that account in our database. Wait for the next update cycle to hit for your logs to show up.";
                    }
                }
            }
        }

        return $this->view->fetch('profile.twig', $args);

    } else {

        return $this->view->fetch('index.twig', $args);
    }
});