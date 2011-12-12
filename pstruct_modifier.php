<?php
	/*
		Modifiers of structure
		Note that 2.x is final release versions
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
	}
	
?>