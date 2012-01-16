<?php
				global $ginfo;
				$value = " ".$packet["message"];
				if(stripos($value,'?') or $mp == true){
					/* Pregunta detectada */
					console('--> Pregunta detectada de '.$sender);
				if(/*(stripos($value,'comando') or stripos($value,'era') or stripos($value,'puedo') or stripos($value,'hago') or stripos($value,'voy') or stripos($value,'podr') or stripos($value,'es')) and */(stripos($value,'cuando') or stripos($value,'cual') or stripos($value,'que') or stripos($value,'para') or stripos($value,'algu') or stripos($value,'como') or stripos($value,'quien') or (stripos($value,'donde') and (stripos($value,'pued') or stripos($value,'podr'))) or stripos($value,'pued')) and !stripos($value,'porque') and !stripos($value,'por que')){
					if(!stripos($value,' tu ') and !stripos($value,'te ') and !stripos($value,' me ')){
						if((stripos($value,'admin') or stripos($value,' mod') or stripos($value,' op') or stripos($value,'staff')) and (stripos($value,'quien') or stripos($value,'cual'))){
							/* Admins */
							privateMessage('los moderadores de este servidor son Duendek86, Zaden, Kreador, susoboiro, creik, LoscoJones, Xerri92, milodescorpio. Dirigete a ellos si tienes problemas.',$sender);
						}
						if(stripos($value,'mcmmo') or stripos($value,'stats') or stripos($value,'abil') or stripos($value,'skill') or stripos($value,'drill') or stripos($value,'berserk')){
							/* McMMO */
							privateMessage('McMMO sirve para dar habilidades especiales tipicas de juegos MMO a Minecraft. Mas info con "/mcc"',$sender);					
						}
						if(stripos($value,'curar') or stripos($value,'sanar')){
							/* curacion */
							privateMessage('puedes curarte en una Residence que pueda curar o comiendo comida. Puedes poner en tu Residence "/res set healing true" para autocurarte',$sender);
						}
						if(stripos($value,'cartel') and stripos($value,'color')){
							/* Colores de carteles */
							privateMessage('puedes poner colores al texto poniendo "&" mas un caracter del 1 a F antes del texto',$sender);
						}
						if(stripos($value,'veteran') and (stripos($value,'como') or stripos($value,'cuando') or stripos($value,'cuanto'))){
							/* Veterano */
							$text = curl_get('http://mespduendedreams.com/test.php');
							$vetearray = explode('</td></tr>',$text);
							foreach($vetearray as $text){
								if(stripos($text,$sender)){
									$days = str_replace(array($sender,' ','<tr>','<td>','</td>','</tr>',"\r","\n"),array('','','','','','','',''),$text);
									break;
								}
							}
							if($days>=30){
							privateMessage('ya eres veterano. Actualmente has pasado '.$days.' dias en el servidor',$sender);
							}else{
							privateMessage('seras veterano cuando hayas pasado 30 dias diferentes en el servidor. Actualmente has pasado '.$days.' dias en el servidor',$sender);
							}
						}
						if(stripos($value,'vip')){
							/* Vips */
							privateMessage('los VIPs tienen acceso a mas comandos, y una atencion mas personalizada. Puedes ser VIP donando al servidor. Mas info en el foro',$sender);
						}
						if(stripos($value,'comando')){
							/* Comandos */
							privateMessage('los comandos que puedes utilizar son (con una barra "/" delante): spawn, home, list, lwc, money, sell, buy, mail, msg, helpop, stats',$sender);
						}
						if(stripos($value,'materia')){
							/* Materiales */
							privateMessage('lo mejor que puedes hacer es ir a una zona algo alejada y talar arboles. Luego replantalos. Tambien puedes comprar.',$sender);
						}
						if(stripos($value,'/res') or stripos($value,'residen')  or (stripos($value,'terren')  or (stripos($value,'prote') and stripos($value,'casa')))){
							/* Residence */
							privateMessage('marca con el hacha de madera la zona. Luego pon "/res create nombre". Mas info con el comando "/res ?"',$sender);
						}					
						if((stripos($value,'prote') or stripos($value,'bloque') or (stripos($value,'magical') and stripos($value,'spell')) or stripos($value,'lwc') or (stripos($value,' no ') and stripos($value,'rob'))) and !stripos($value,' bloque')){
							/* LWC */
							privateMessage('puedes proteger cofres, puertas, dispensadores, hornos y carteles con LWC. Pon primero "/cremove" y luego "/cprivate". Mas info con "/lwc"',$sender);
						}
						if(stripos($value,' ts') or stripos($value,'turnstile') or (stripos($value,'boton') and (stripos($value,'pag') or stripos($value,'cobr') or stripos($value,'cost') or stripos($value,'cueste')))){
							/* TurnStile*/
							privateMessage('apunta a una valla y pon "/ts make nombre". Luego apunta el boton y pon "/ts link nombre". Mas info con "/ts"',$sender);					
						}
						if(stripos($value,'pay') and stripos($value,'day')){
							/* PayDay */
							privateMessage('el Pay Day te da dinero al inicio de cada hora que estes conectado. Puedes ver la cantidad de Mesps que te da con "/pd"',$sender); 				
						}
						if(stripos($value,'lobo') and (stripos($value,'hay') or stripos($value,'enc') or stripos($value,'sale'))){
							/* Lobos */
							privateMessage('los lobos se encuentran en la Taiga y los Bosques. Para mayor seguridad, ves a una zona con nieve.',$sender);					
						}
						if(stripos($value,'diner') or (stripos($value,'mesp') and !stripos($value,'algu') and !stripos($value,'city') and !stripos($value,'3') and !stripos($value,'stack') and !stripos($value,'unidad') and !stripos($value,'ciud') and !stripos($value,'nuev')) or stripos($value,'credit') or stripos($value,'money')){
							/* Dinero */
							if(stripos($value,'gan') or stripos($value,'cons') or stripos($value,'rico') or stripos($value,'mas')){
							privateMessage('puedes ganar dinero vendiendo bloques en tiendas, o con el Pay Day. Al inicio de cada hora te daran mesps',$sender);
							}else{
							privateMessage('para saber cuantos Mesps tienes pon "/money". Mas info con "/money ?"',$sender);
							}
						}
						if(stripos($value,' spawn') or stripos($value,' inicio')){
							/* Spawn */
							privateMessage('para ir al Spawn pon el comando "/spawn" y espera 5 segundos sin moverte',$sender);				 
						}
						if(stripos($value,'pued') and (stripos($value,'constr') or stripos($value,'terreno') or stripos($value,'parce') or stripos($value,'casa'))){
							/* Construir casa */
							privateMessage('puedes construir donde no molestes a nadie. Tambien puedes alquilar una parcela en una ciudad',$sender);					
						}
						if(((stripos($value,'hay') and stripos($value,'tienda')) or stripos($value,'compr')) and !stripos($value,'terren') and !stripos($value,'casa') and !stripos($value,'parcel') and !stripos($value,'compras')){
							/* Tiendas */
							privateMessage('puedes comprar en las tiendas. Para ver la lista, pon "bot tiendas"',$sender); 				
						}
						if(stripos($value,'casa') or stripos($value,'home')){
							/* Home */
							/*if(stripos($value,'invit') or stripos($value,'otr')){
								//invitar 
								privateMessage('para invitar a otra persona a tu casa, pon "/home invite nombre"',$sender);	
							}else*/if(stripos($value,'poner') or stripos($value,'volver') or stripos($value,'voy') or stripos($value,'ir') or stripos($value,'vuelvo')){
								/* poner la casa, volver */
								privateMessage('puedes establecer tu casa donde estas con "/sethome". Luego puedes volver con "/home"',$sender);	
							}
						}
						if(stripos($value,'lava') and stripos($value,'infin') and !stripos($value,'obsi')){
							privateMessage('no se puede hacer lava infinita.',$sender);	
						}
						if(stripos($value,'afk')){
							privateMessage('AFK significa Away From Keyboard, es decir, lejos del teclado.',$sender);
						}
						if(stripos($value,'foro')){
							privateMessage('el foro de este servidor es http://minecraft-esp.com/',$sender);
						}
						if((stripos($value,'podria') or stripos($value,'puedo')) and stripos($value,'robar')){
							privateMessage('NO',$sender);
						}
					}
					if((stripos($value,'progra') or stripos($value,'cre') or stripos($value,'hizo'))){
						privateMessage('me creo shoghicp. Estoy programado en PHP',$sender);	
					}
					if((stripos($value,'llama') or stripos($value,'nombre') or stripos($value,'cread') or stripos($value,'eres'))){
						privateMessage('me llamo Bot Ayuda, y estoy aqui para ayudarte en tu inicio.',$sender);	
					}
					if((stripos($value,' dia') or stripos($value,'noche') or stripos($value,'hora') or stripos($value,'tiempo'))){
						privateMessage('son las '.((intval($ginfo["time"]/1000+6) % 24)).':'.str_pad(intval(($ginfo["time"]/1000-floor($ginfo["time"]/1000))*60),2,"0",STR_PAD_LEFT).' y es de '.(($ginfo["time"] > 23100 or $ginfo["time"] < 12900) ? "dia":"noche"),$sender); 
					}				
				}
}
				if(stripos($value,'gay')){
					privateMessage('soy un robot. No se que opinaras tu sobre ti.',$sender);	
				}

?>