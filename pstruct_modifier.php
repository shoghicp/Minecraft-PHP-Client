<?php
	/*
		Modifiers of structure

	*/
	if($protocol >= 23){
		unset($pstruct["1b"]);
	}
	
	if($protocol <= 23){
		$pstruct["01"] = array(
			"int",
			"string",
			"long",
			"string",
			"int",
			"byte",
			"byte",
			"ubyte",
			"ubyte",
		);
		$pstruct["09"] = array(
			"byte",
			"byte",
			"byte",
			"short",
			"long",
			"string",
		);
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
		$pstruct["33"] = array(
			"int",
			"short",
			"int",
			"byte",
			"byte",
			"byte",
			"int",
			"chunkArray",
		);
	
		$pstruct["34"] = array(
			"int",
			"int",
			"short",
			"multiblockArray",
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
