<?php
	function command($command, $value, $owner = true){
		global $arguments, $ginfo, $permissions, $buffer, $players, $position_packet, $colorchar, $entities, $path;
		$commands = array( //3 = owner, 2 = mod, 1 = normal
			"follow" => 2,
			"fly" => 2,
			"dump" => 3,
			"path" => 3,
			"restart" => 3,
			"attack" => 2,
			"aura" => 3,
			"crazyness" => 2,
			"minecart" => 2,
			"say" => 2,
			"hola" => 1,
			"adios" => 1,
			"ayuda" => 1,
			"dado" => 1,
			"tonto" => 1,
			"chiste" => 1,
			"comida" => 1,
			"fail" => 2,
			"die" => 3,
			"version" => 1,
			"coord" => 1,
			"block" => 1,
		);
		$clist = "";
		foreach($commands as $c=>$p){
			$clist .= ", ".$c.str_repeat("*", $p-1);
		}
		$clist = substr($clist,2);
		
		$permissions[$owner] = !isset($permissions[$owner]) ? 1:$permissions[$owner];
		if(isset($commands[$command]) and $commands[$command] > $permissions[$owner]){
			return false;
		}
		
		$continue = true;
		switch($command){
			case "block":
				global $chunks, $material;
				$block = chunk_get_block($position_packet["x"], $position_packet["y"] - 1, $position_packet["z"]);
				privateMessage("Estoy encima de: ".$material[$block]." (".$block.")",$owner);
				break;
			case "fly":
				$ginfo["fly"] = $ginfo["fly"] == true ? false:true;
				break;
			case "comida":
				if(sqrt(pow(abs($position_packet["x"]-$entities[$players[$owner]]["x"]),2)+pow(abs($position_packet["z"]-$entities[$players[$owner]]["z"]),2)) > 6){
					privateMessage('Estas muy lejos',$owner);
					break;
				}
				if($ginfo["timer"]["food".$owner]>time()){
					privateMessage('Espera un poco',$owner);
					break;
				}
				$ginfo["timer"]["food".$owner] = $time + 10;
				
				$food = array(
					282 => 12, //Stew
					364 => 12, //Steak
					320 => 12, //Porkchop
					366 => 14, //Chicken
					297 => 15, //Bread
					350 => 15, //Fish
					260 => 16, //R Apple
					322 => 16, //G Apple
					363 => 17, //Raw Beef
					319 => 17, //Raw Porkchop
					360 => 18, //Melon
					349 => 18, //Raw fish
					265 => 18, //Raw Chicken
					357 => 19, //Cookie
					367 => 16, //Flesh
					375 => 18, //Spider eye,					
				);
				$food = array_reverse($food, true);
				$eat = false;
				for($i=36;$i<=44;++$i){
					$slot = $ginfo["inventory"][$i];
					foreach($food as $item => $minhealth){
						if($slot[0] == $item){
							write_packet("10",array("slot" => $i-36));
							write_packet("0e", array("status" => 4, "x" => 0, "y" => 0, "z" => 0, "face" => 0));
							$eat = true;
							break;
						}
					}
					if($eat == true){
						break;
					}
				}
				if($eat == false){
					privateMessage('Lo siento, no tengo comida',$owner);
				}
				break;
			case "jump":
				/*global $continue, $sock;
				$buffer .= "\xff".write_string("Stopped by ".$owner);
				$b = "";
				for($i=0;$i<100;++$i){
					$b .= "\xff".write_string("a");
				}
				socket_write($sock, $b);
				$continue = false;			*/
				//$arguments["commands"]["log"] = false;
				/*
				global $position_packet;
				$position_packet["pitch"] = 3.402823E+038F;
				$position_packet["yaw"] = 3.402823E+038F;
				$position_packet["jump"] = false;
				write_packet("0d", $position_packet);*/
				break;
			case "dump":
				$arguments["commands"]["dump"] = $arguments["commands"]["dump"] == false ? true:false;
				$continue = false;
				break;
			case "path":
				global $recorder;
				$c = explode(" ",$value);
				switch($c[0]){
					case "record":
						if($c[1] != ""){							
							$recorder["mode"] = "record";
							$recorder["name"] = $c[1];
							$recorder["positions"] = array();
						}
						break;
					case "stop":
						if($recorder["mode"] == "record"){
							file_put_contents($path."/paths/".$recorder["name"].".path",serialize($recorder["positions"]));
							$recorder["mode"] = "";
						}elseif($recorder["mode"] == "play"){
							$recorder["mode"] = "";
						}
						break;
					case "play":
						if($c[1] != ""){
							$recorder["mode"] = "play";
							$recorder["name"] = 0;
							$recorder["positions"] = unserialize(file_get_contents($path."/paths/".$c[1].".path"));
						}
						break;
				}
				break;
			case "restart":
				global $restart;
				$restart = true;
				Message("*Reiniciando*");
				break;
			case "follow":
				if($ginfo["follow"]>0){
					$ginfo["follow"] = 0;
				}else{
					if(isset($players[$value])){
						$ginfo["follow"] = $players[$value];
					}else{
						$ginfo["follow"] = $ginfo["owner"]["eid"];
					}
				}
				$continue = false;
				break;
			case "aura":
				$ginfo["aura"] = $ginfo["aura"] == true ? false:true;
				break;
			case "attack":
				if($ginfo["follow"]>0){
					$ginfo["follow"] = 0;
					$ginfo["attack"] = false;
				}else{
					if(isset($players[$value])){
						$ginfo["follow"] = $players[$value];
					}else{
						$ginfo["follow"] = $ginfo["owner"]["eid"];
					}
					$ginfo["attack"] = true;
				}
				$continue = false;
				break;
			case "say":
				Message($value);
				$continue = false;
				break;
			case "hola":
			case "saluda":
				privateMessage('Hola '.$owner,$owner);
				$continue = false;
				break;
			case "adios":
				privateMessage('Adios '.$owner,$owner);
				$continue = false;
				break;
			case "coord":
			case "coords":
				if(!$entities[$players[$owner]]){
					break;
				}
				$p = $entities[$players[$owner]];
				if($value=="public"){
					Message('Ultimas coordenadas conocidas de '.$owner.': x='.intval($p['x']).' y='.intval($p['y']).' z='.intval($p['z']));
				}elseif($permissions[$owner] >= 2 and isset($players[$value])){
					$p = $entities[$players[$value]];
					privateMessage('Ultimas coordenadas conocidas de '.$value.': x='.intval($p['x']).' y='.intval($p['y']).' z='.intval($p['z']), $owner);
				}else{
					privateMessage('Tus ultimas coordenadas conocidas: x='.intval($p['x']).' y='.intval($p['y']).' z='.intval($p['z']),$owner);				
				}
				break;
			case 'ayuda':
			case "help":
				privateMessage('Comandos del bot actuales: '.$clist,$owner);
				$continue = false;
				break;
			case 'dado':
				Message('El '.mt_rand(1,((intval($value)>0) ? intval($value):6)));
				$continue = false;
				break;
			case "crazyness":
				$arguments["commands"]["crazyness"] = $value;
				$continue = false;
				break;
			case "minecart":
				if($value == "enter"){
					$into = 0;
					$distT = 100000;
					foreach($entities as $eid => $data){
						if($data["type"] == 10){
							$dist = sqrt(pow($data["x"],2)+pow($data["z"],2));
							if($dist <= 4 and $dist < $distT){
								$into = $eid;
								$distT = $dist;
							}
						}
					}
					if($eid > 0){
						write_packet("27", array(
							"eid" => $ginfo["eid"],
							"vid" => $into,
						));
					}
				}else{
					write_packet("27", array(
							"eid" => $ginfo["eid"],
							"vid" => -1,
					));
				}
				break;
			case 'tonto':
				privateMessage($owner.', tu mas',$owner);
				$continue = false;
				break;	
			case 'chiste':
				$chiste = array(
					"El dinero no hace la felicidad pero es mejor llorar en un Ferrari.",
					"Era un cocinero tan feo, pero tan feo, que hacía llorar a las cebollas.",
					"Voy volando. Firmado, Palomo.",
					"Cuantos son 99 en chino? caxi xien.",
					"Era un señor tan sordo, tan sordo, que contestaba al teléfono aunque no sonara.",
					"Como se llama el padre de ete? Donete.",
					"¿Cuánto es 4x4? Empate!!! ¿Y 2x1? Oferta!!",
					"Que le dice un árbol a otro? Ponte el chubasquero que viene un perro",
					"Como se dice en aleman autobus!   ...subenpagenestrugenbagen",
					"¿Que le dice una ficha de puzzle a otra? Solo hay un sitio para mi",
					"Trololololololololololo... Trololololo... Hahaha!!",
					"Llega corriendo Pepita a su casa: ¿Mama, mama, es verdad que soy huerfana...?",
					"- Y tu, ¿como te llamas? - Yo, Bienvenido. - ¡Anda! ¡como mi felpudo!",
					"El coronel dijo: ¡Sigan avanzando! Y todos se perdieron, porque Vanzando no se sabía el camino.",
					"Están en un barco y dice el capitán: -Subid las velas!! Y los de abajo se quedaron a oscuras",
					"Entra uno a un bar de pinchos y... hay ! huy! ahy ! huy!",
					"Había una vez un señor tan gordo, que cada vez que daba una vuelta era su cumpleaños.",
					"Había una vez un hombre tan feo, tan feo, que fue a un concurso de feos y lo perdió por feo.",
					"Había una vez una persona tan pobre, tan pobre, tan pobre que no tenia ni hambre.",
					"Había una vez una vaca que se comió un vidrio, y la leche le salió cortada.",
					"- Ay, cariño... No sé que sería el tiempo sin ti... - Pues que va a ser: empo!!!",
				);
				privateMessage($chiste[mt_rand(0,count($chiste)-1)],$owner);
				$continue = false;
				break;
			case "die":
				Message("Adios, mundo cruel!");
				$buffer .= "\xff".write_string("Stopped by ".$owner);
				write_packet("ff", array("message" => "Adios, mundo cruel!"));
				$continue = false;
				break;
			case "fail":
				privateMessage("Error escrito en log", $owner);
				console("-----------BOT ERROR by ".$owner."---------------");
				$continue = false;
				break;
			case "version":
				privateMessage("Minecraft PHP Client + BotAyuda v".VERSION,$owner);
				$continue = false;
				break;
			default:
				if(strpos($value,'+') !== false or strpos($value,'-') !== false or strpos($value,'*') !== false or strpos($value,'/') !== false or strpos($value,'%') !== false or strpos($value,'<') !== false or strpos($value,'>') !== false or strpos($value,'=') !== false){
					privateMessage('Resultado: '.eval('$ret='.str_replace(array('"',"'","(",")","\$",'?','!',";"),'',$value).';return $ret;'),$owner);
					$continue = false;
				}
				break;			
		}
		
		if($continue==false){
			return true;
		}else{
			return false;
		}
		
	}
?>