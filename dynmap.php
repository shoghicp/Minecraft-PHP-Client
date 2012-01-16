<?php

function DynMapCoords(){
	$config = array("get" => 'up/world/{world}/', 'send' => 'up/sendmessage');
	$worlds = array(
		"mesp",
		/*"Celestia",
		"Reach",
		"Raccon",
		"Splash",
		"mesp2_the_end",
		"Laisla",
		"nether",*/
	);
	$players = array();
	foreach($worlds as $world){
		$update = json_decode(curl_get("http://mespduendedreams.com:8197/".str_replace('{world}',$world,$config['get']).time()),true);
		foreach($update['players'] as $player){
			$players[$player['name']] = $player;
		}	
	}
	return $players;
}

?>