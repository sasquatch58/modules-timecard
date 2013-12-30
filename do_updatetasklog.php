<?php
/**
* Adapted for Web2project by MiraKlim
* Eureka with his colleagues from his company (DILA) - Store task progress
**/


$del = w2PgetParam( $_POST, 'del', 0 );

$hdlog = w2PgetParam( $_POST, 'task_log_help_desk_id', 0 );

if ($hdlog) {
	require_once( $AppUI->getModuleClass( 'helpdesk' ) );
	$obj = new CHDTaskLog();
}
else {
	require_once( $AppUI->getModuleClass( 'tasks' ) );
	$obj = new CTask_Log();
}
$isOk = $obj->bind( $_POST );

if (!$isOk) {
	$AppUI->setMsg( $obj->getError(), UI_MSG_ERROR );
	$AppUI->redirect();
}

if ($obj->task_log_date) {
	$date = new w2p_Utilities_Date( $obj->task_log_date );
	$obj->task_log_date = $date->format( FMT_DATETIME_MYSQL );
}

$tid = $obj->task_log_task;
$task = new CTask(); 
$tpc = w2PgetParam( $_POST, 'task_percent_complete', 0 );
$isTaskOk = $task->load($tid);
$TaskToSave = false;
if ($isTaskOk) {
	$prevtpc = $task->task_percent_complete;
	if ($prevtpc != $tpc) {
		$task->task_percent_complete = $tpc;
		$TaskToSave=true;
	}
}

// prepare (and translate) the module name ready for the suffix
$AppUI->setMsg( 'Task Log' );
if ($del) {
	if (($msg = $obj->delete())) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
	} else {
		$AppUI->setMsg( "deleted", UI_MSG_ALERT );
	}
	$AppUI->redirect("m=timecard&tab=0");
} else {
	$msg = $obj->store();
	if ($msg !==true) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
		$AppUI->redirect();
	} else {
		if ($TaskToSave){
			$task->store();
		}
		$AppUI->setMsg( @$_POST['task_log_id'] ? 'updated' : 'inserted', UI_MSG_OK, true );
		$AppUI->redirect("m=timecard&tab=0");
	}
}

$AppUI->redirect();