<?php
/*
-------------- Ralph  ---------------
An interface for Runescape web data

    Copyright (c) 2017 @dreadnip
            License: MIT
https://github.com/dreadnip/ralph
*/

namespace Ralph;

require __DIR__.'/../vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;

Class api
        {
    //	Default Guzzle client & base URIs
	private $client = null;
    private $base_legacy_url = 'http://services.runescape.com/';
	private $base_runemetrics_url = 'https://apps.runescape.com/runemetrics/';
	private $skill_list = ["Overall", "Attack", "Defence", "Strength", "Hitpoints", "Ranged", "Prayer", "Magic", "Cooking", "Woodcutting", "Fletching", "Fishing", "Firemaking", "Crafting", "Smithing", "Mining", "Herblore", "Agility", "Thieving", "Slayer", "Farming", "Runecrafting", "Hunter", "Construction", "Summoning", "Dungeoneering", "Divination", "Invention", "Bounty Hunter", "B.H. Rogues", "Dominion Tower", "The Crucible", "Castle Wars games", "B.A. Attackers", "B.A. Defenders", "B.A. Collectors", "B.A. Healers", "Duel Tournament", "Mobilising Armies", "Conquest", "Fist of Guthix", "GG: Athletics", "GG: Resource Race", "WE2: Armadyl Lifetime Contribution", "WE2: Bandos Lifetime Contribution", "WE2: Armadyl PvP kills", "WE2: Bandos PvP kills", "Heist Guard Level", "Heist Robber Level", "CFP: 5 game average", "AF15: Cow Tipping", "AF15: Rats killed after the miniquest"];
	//	Debug output
        	public $request_info = [];

	/*
     *  Constructor
     */
    
	function __construct()
	{
		$this->client =  new Client(['http_errors' => false]);
	}

    /*
     *  base functions
     */

    public function get_json($url, $trim_callback = false)
    {
		//perform the request
    	$response = $this->client->request('GET', $url);

		//check for a good HTTP code
    	if ($response->getStatusCode() == 200) {

			//get the response content
    		$body = $response->getBody()->getContents();

		    //if this is a callback response, trim it
    		if ($trim_callback == true) {
    			$body = $this->trim_callback($body);
    		}

		    //decode the content
    		$content = json_decode($body);

		    //if there are errors, return them as a property of an object
    		if (isset($content->error)) {
    			return (object)["error" => $content->error];
    		} else {
    			return $content;
    		}
    	} else {
    		return false;
    	}
    }

	/*
     *  Player functions
     */

	/**
	 * Get the Runemetrics profile data for a given player name
	 * @param string $player_name
	 * @return object
	 */
	public function get_profile($player_name)
	{
		return $this->get_json($this->base_runemetrics_url.'profile/profile?user='.$this->norm($player_name).'&activities=20');
	}

    public function get_avatar($player_name)
    {
    	return $this->base_legacy_url.'m=avatar-rs/'.$this->norm($player_name).'/chat.png';
    }

    public function get_bulk_profiles($list)
    {
        $output = [];

        $client = new Client(['base_uri' => 'https://apps.runescape.com/']);

        $requests = function ($list)
        {
            foreach ($list as $player_name => $player_id) {
                yield new Request('GET', $this->base_runemetrics_url.'profile/profile?user='.$this->norm($player_name).'&activities=20');
            }
        };

        $pool = new Pool($client, $requests($list), [
            'concurrency' => 25,
            'fulfilled' => function ($response, $index) use ($list, &$output)
            {

                if ($response->getStatusCode() == 200) {

                    $profile = json_decode($response->getBody()->getContents());
                    if(!isset($profile->error)){
                    	$output_object = (object)[];
                        $output_object->id = $list[$this->norm($profile->name)];
                        $output_object->activities = $profile->activities;
                        $output[] = $output_object;
                    }
                }
            },
            'rejected' => function ($reason, $index)
            {

               //$s['fail']++;
            }
        ]);

        $promise = $pool->promise();
        $promise->wait(); //push the button

        return $output;
    }

	/*
	 *  Helper functions
	 */

	public function norm($string)
	{
		return str_replace(' ', '+', htmlentities(utf8_encode(strtolower($string))));
	}

}