<?php
	/*
		Modifiers of structure

	*/
	
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