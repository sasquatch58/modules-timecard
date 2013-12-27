<?php /* TIMECARD $Id$ */
/**
* Adapted for Web2project by MiraKlim
This file does no action in itself.
If it is accessed directly it will give a summary of the module parameters.
**/

// MODULE CONFIGURATION DEFINITION
$config = array();
$config['mod_name'] = 'TimeCard';
$config['mod_version'] = '3.0';
$config['mod_directory'] = 'timecard';
$config['mod_setup_class'] = 'CSetupTimeCard';
$config['mod_type'] = 'user';
$config['mod_ui_name'] = 'Time Card';
$config['mod_ui_icon'] = 'TimeCard.png';
$config['mod_description'] = $AppUI->_('Time Card allows easy access to a weekly timecard based on existing task logs.');
$config['mod_config'] = true;

if (@$a == 'setup') {
	echo w2PshowModuleConfig( $config );
}

/*
// MODULE SETUP CLASS
	This class must contain the following methods:
	install - creates the required db tables
	remove - drop the appropriate db tables
	upgrade - upgrades tables from previous versions
*/
class CSetupTimeCard
{
/*
	Install routine
*/
	function install()
	{
		global $AppUI;
		$perms = $AppUI->acl();
		return $perms->registerModule('TimeCard', 'timecard');
	}
/*
	Removal routine
*/
	function remove()
	{
		global $AppUI;
		$perms = $AppUI->acl();
		return $perms->unregisterModule('timecard');
	}
/*
	Upgrade routine
*/
	function upgrade()
	{
		return false;
	}

	function configure()
	{
		global $AppUI;
		$AppUI->redirect("m=timecard&a=configure");
		return true;
	}
}

?>