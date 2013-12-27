<?php

// Function to build a where clause to be appended to any sql that will narrow
// down the returned data to only permitted entities

function getPermsWhereClause($mod, $mod_id_field)
{
	global $AppUI, $perms;
	return "1 = 1";	
}
?>