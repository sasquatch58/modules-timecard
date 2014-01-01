<?php

if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

function sendError($message) {
	header("Content-type: text/plain; charset=utf-8");
	echo "Error: ".$msg;
	exit;
}

$del = w2PgetParam( $_POST, 'del', 0 );
$notify_owner = w2PgetParam($_POST, 'task_log_notify_owner', 'off');

$hdlog = w2PgetParam( $_POST, 'task_log_help_desk_id', 0 );

if ($hdlog) {
	$obj = new CHDTaskLog();
}
else {
	$obj = new CTask_Log();
}
$isOk = $obj->bind( $_POST );

if (!$isOk) {
	sendError($obj->getError());
}

if ($obj->task_log_date) {
	$date = new w2p_Utilities_Date( $obj->task_log_date );
}
else {
	$date = new w2p_Utilities_Date( );
}
$obj->task_log_date = $date->format( FMT_DATETIME_MYSQL );

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
	$project = new CProject(); 
	$isProjectOk = $project->load($task->task_project);
}

// prepare (and translate) the module name ready for the suffix
$AppUI->setMsg( 'Task Log' );
if ($del) {
	$msg = $obj->delete();
	if ($msg === false) {
		sendError($AppUI->_('You do not have permission to delete this task log', UI_OUTPUT_JS));
	}
	elseif ($msg !==true) {
		sendError($msg);
	}
	else {
		header("Content-type: text/plain; charset=utf-8");
		echo "deleted";
		exit;
	}
} else {
	$msg = $obj->store();
	if ($msg !==true) {
		sendError($msg);
	}
	else {
		if ($TaskToSave){
			$task->store();
		}
	}
}
$result = array();
$vars = get_object_vars($obj);
foreach($vars as $key => $val) {
	if (!is_object($val)) {
		$result[$key] = $val;
	}
}
if ($isTaskOk) {
	// Check if we need to email the task log to anyone.
	if ('on' == $notify_owner) {
		$msg = $task->notifyOwner();
	}
	$email_assignees        = w2PgetParam($_POST, 'email_assignees', null);
	$email_task_contacts    = w2PgetParam($_POST, 'email_task_contacts', null);
	$email_project_contacts = w2PgetParam($_POST, 'email_project_contacts', null);
	$email_others           = w2PgetParam($_POST, 'email_others', '');
	$email_log_user         = w2PgetParam($_POST, 'email_log_user', '');
	$task_log_creator       = (int) w2PgetParam($_POST, 'task_log_creator', 0);
	$email_extras           = w2PgetParam($_POST, 'email_extras', null);
	// Email the user this task log is being created for, might not be the person
	// creating the logf
	$user_to_email = 0;
	if (isset($email_log_user) && 'on' == $email_log_user && $task_log_creator) {
		$user_to_email = $task_log_creator;
	}
	$task->email_log($obj, $email_assignees, $email_task_contacts, $email_project_contacts, $email_others, $email_extras, $user_to_email);
	$vars = get_object_vars($task);
	foreach($vars as $key => $val) {
		if (!is_object($val)) {
			$result[$key] = $val;
		}
	}
	if ($isProjectOk) {
		$vars = get_object_vars($project);
		foreach($vars as $key => $val) {
			if (!is_object($val)) {
				$result[$key] = $val;
			}
		}
	}
}
header("Content-type: application/json; charset=utf-8");
echo json_encode($result);
exit;