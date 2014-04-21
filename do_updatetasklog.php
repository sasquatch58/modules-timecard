<?php
/**
* Adapted for Web2project by MiraKlim
* Eureka with his colleagues from his company (DILA) - Store task progress
**/


$del = w2PgetParam( $_POST, 'del', 0 );

$hdlog = w2PgetParam( $_POST, 'task_log_help_desk_id', 0 );

if ($hdlog) {
	$obj = new CHDTaskLog();
}
else {
	$obj = new CTask_Log();
}
$isOk = $obj->bind( $_POST );

if (!$isOk) {
	$AppUI->setMsg( $obj->getError(), UI_MSG_ERROR );
    $AppUI->redirect("m=timecard&tab=0");
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
	if ($obj->delete()) {
        $AppUI->setMsg( "deleted", UI_MSG_ALERT );
	} else {
        $AppUI->setMsg( $msg, UI_MSG_ERROR );
	}
} else {
	if ($obj->store()) {
        if ($TaskToSave){
            $task->store();
        }
        $AppUI->setMsg( @$_POST['task_log_id'] ? 'updated' : 'inserted', UI_MSG_OK, true );
	} else {
        $AppUI->setMsg( $msg, UI_MSG_ERROR );
	}
}

$AppUI->redirect("m=timecard&tab=0");