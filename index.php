<?php /* TIMECARD $Id$ */
/**
* Adapted for Web2project by MiraKlim
* Adapted for Web2project 2.1 by Eureka and colleagues from his company (DILA)
**/

global $newTLogTabNum;
$newTLogTabNum = 2;

// check permissions
$canRead = canView($m);
$canEdit = canEdit($m);

if (!$canRead) {
	$AppUI->redirect("m=help&a=access_denied");
}

$TIMECARD_CONFIG = array();
require_once "./modules/timecard/config.php";

// setup the title block
$titleBlock = new w2p_Theme_TitleBlock('Time Card', 'TimeCard.png', $m, "$m.$a");

$titleBlock->show();

if (isset( $_GET['tab'] )) {
	$AppUI->setState('TimecardVwTab', $_GET['tab']);
}
$tab = $AppUI->getState('TimecardVwTab') ? $AppUI->getState('TimecardVwTab') : 0;

$tabBox = new CTabBox("?m=timecard&userid=" . $AppUI->user_id, "./modules/timecard/", $tab);
$tabBox->add('vw_timecard', 'Weekly Time Card');
$tabBox->add('vw_calendar_by_user', 'Task Logs by Date');
if ($TIMECARD_CONFIG['minimum_report_level'] >= $AppUI->user_type) {
	$tabBox->add('vw_weekly_by_user', 'Summary by User');
	$tabBox->add('vw_weekly_by_project', 'Summary by Project');
	$newTLogTabNum = 4;
}
$tabBox->add('vw_newlog', 'New Task Log');
if ( $AppUI->isActiveModule('helpdesk')) {
	$tabBox->add( 'vw_newhelpdesklog', 'New Helpdesk Log' );
}

$tabBox->show();