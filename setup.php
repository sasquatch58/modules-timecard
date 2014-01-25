<?php /* TIMECARD $Id$ */
/* 
* Adapted for Web2project by MiraKlim
This file does no action in itself.
If it is accessed directly it will give a summary of the module parameters.
*/

// MODULE CONFIGURATION DEFINITION
$config = array();
$config['mod_name'] 			= 'TimeCard';     	    // name the module
$config['mod_version'] 			= '3.0';                // add a version number
$config['mod_directory'] 		= 'timecard';           // tell web2project where to find this module
$config['mod_setup_class'] 		= 'CSetupTimeCard';     // the name of the PHP setup class (used below)
$config['mod_type'] 			= 'user';               // 'core' for modules distributed with w2p by standard, 'user' for additional modules
$config['mod_ui_name'] 			= $config['mod_name'];  // the name that is shown in the main menu of the User Interface
$config['mod_ui_icon'] 			= 'TimeCard.png';       // name of a related icon
$config['mod_description']      = $AppUI->_('Time Card allows easy access to a weekly timecard based on existing task logs.');
//$config['mod_main_class'] = '';	                    //no main class for timecard
$config['mod_config']           = true;                 // show 'configure' link in viewmods
$config['requirements'] = array(
		array('require' => 'web2project',   'comparator' => '>=', 'version' => '3')
);

if (@$a == 'setup') {
	echo w2PshowModuleConfig( $config );
}

/**
 * Class CSetupTimeCard
 */
class CSetupTimeCard extends w2p_System_Setup {
	public function install() {
		$result = $this->_meetsRequirements();
			if (!$result) {
			return false;
		}
		return parent::install();
	}
		
	public function configure() {
        $this->_AppUI->redirect("m=timecard&a=configure");
        return parent::configure();
	}
	
    public function upgrade($old_version) {
        return false;
	}
	
	public function remove() {
		return parent::remove();
	}
}