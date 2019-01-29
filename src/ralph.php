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

    public function get_raw($url)
    {
        $response = $this->client->request('GET', $url);
        if ($response->getStatusCode() == 200) {
            $body = $response->getBody();
            return $body->getContents();
        } else {
            return false;
        }
    }
    
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

    public function get_details($player_name)
    {
        return $this->get_json($this->base_legacy_url.'m=website-data/playerDetails.ws?membership=true&names=["'.$this->norm($player_name).'"]&callback=angular.callbacks._0', true)[0];
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
            foreach ($list as $player_obj) {
                yield new Request('GET', $this->base_runemetrics_url.'profile/profile?user='.$this->norm($player_obj->us_name).'&activities=20');
            }
        };

        $pool = new Pool($client, $requests($list), [
            'concurrency' => 25,
            'fulfilled' => function ($response, $index) use ($list, &$output)
            {
                $output_object = (object)[];
                $output_object->index = $index;
                $output_object->id = $list[$index]->us_id;
                $output_object->name = $list[$index]->us_name;
                $output_object->clan = $list[$index]->us_clan;

                if ($response->getStatusCode() == 200) {

                    $profile = json_decode($response->getBody()->getContents());
                    if(!isset($profile->error)){
                        $output_object->activities = $profile->activities;
                    } else {
                        $output_object->error = $profile->error;
                    }
                }

                $output[] = $output_object;
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

    public function get_clan_list($clan_name)
    {
        $raw_list = $this->get_raw($this->base_legacy_url.'m=clan-hiscores/members_lite.ws?clanName='.$this->norm($clan_name));
        $check = explode(",", $raw_list, 2);
        if ($check[0] == 'Clanmate') {
            $clan_list = [];
            $raw_list = explode("\n", $raw_list);
            foreach ($raw_list as $key => $row) {
                if ($key != 0 && $key <= (count($raw_list)-2)) {
                    $row_item = explode(",", $row);
                    $name = htmlentities(utf8_encode($row_item[0]));
                    $clan_list[] = (object)array(
                        'name' => str_replace('&nbsp;', ' ', $name),
                        'rank' => $row_item[1],
                        'clan_xp' => $row_item[2],
                        'clan_kills' => $row_item[3]
                    );
                }
            }
            return $clan_list;
        } else {
            return (object)['error' => 'CLAN NOT FOUND'];
        }
    }

	/*
	 *  Helper functions
	 */

	public function norm($string)
	{
		return str_replace(' ', '+', htmlentities(utf8_encode(strtolower($string))));
	}

    private function trim_callback($response_string)
    {
        $response_string = substr($response_string, 21);
        $response_string = substr($response_string, 0, -3);
        return $response_string;
    }

}