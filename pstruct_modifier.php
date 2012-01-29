<?php
	/*
		Modifiers of structure

	*/
	if($protocol >= 23){
		unset($pstruct["1b"]);
	}
	
	if($protocol <= 23){
		$pstruct["18"] = array(
			"int",
			"byte",
			"int",
			"int",
			"int",
			"byte",
			"byte",
			"entityMetadata",
		);
	}
	
	if($protocol <= 22){
		$pstruct["09"] = array(
			"byte",
			"byte",
			"byte",
			"short",
			"long",
		);
		$pstruct["01"] = array(
			"int",
			"string",
			"long",
			"int",
			"byte",
			"byte",
			"ubyte",
			"ubyte",
		);	
	}
	
	if($protocol <= 17){
		$pstruct["2b"] = array(
			"byte",
			"byte",
			"short",
		);
	}
	if($protocol <= 14){
		$pstruct["00"] = array();
		$pstruct["01"] = array(
			"int",
			"string",
			"long",
			"byte",
		);
		$pstruct["46"] = array(
			"int",
		);
	}
	
?>