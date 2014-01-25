<?php
/**
* Adapted for Web2project by MiraKlim
* Adapted for Web2project 2.1 by Eureka
*/
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}
global $TIMECARD_CONFIG, $newTLogTabNum, $AppUI;

$m = $AppUI->checkFileName(w2PgetParam( $_GET, 'm', getReadableModule() ));
$canEdit = canEdit( $m );
if (!$canEdit) {
    $AppUI->redirect( "m=public&amp;a=access_denied" );
}
// check permissions
$canEdit = canEdit('task_log');
$canAdd = canAdd('task_log');

//grab hours per day from config
$min_hours_day = w2PgetConfig("daily_working_hours");
$can_edit_other_timesheets = $TIMECARD_CONFIG['minimum_edit_level']>=$AppUI->user_type;
$show_other_worksheets = $TIMECARD_CONFIG['minimum_see_level']>=$AppUI->user_type;
$show_possible_hours_worked = $TIMECARD_CONFIG['show_possible_hours_worked'];
$show_only_calendar_working_days = $TIMECARD_CONFIG['show_only_calendar_working_days'];
$helpdesk_available = $AppUI->isActiveModule('helpdesk'); 
$log_ignore = w2PgetParam( $_GET, 'log_ignore', 0 );
$taskLogReference = w2PgetSysVal('TaskLogReference');

//compute hours/week from config
$min_hours_week = count(explode(",",w2PgetConfig("cal_working_days"))) * $min_hours_day;

// get date format
$df = $AppUI->getPref('SHDATEFORMAT');

if (isset( $_GET['user_id'] )) {
    $AppUI->setState( 'TimecardSelectedUser', $_GET['user_id'] );
}
$user_id = $AppUI->getState( 'TimecardSelectedUser' ) ? $AppUI->getState( 'TimecardSelectedUser' ) : $AppUI->user_id;

$AppUI->savePlace();

if (isset( $_GET['start_date'] )) {
    $AppUI->setState( 'TimecardStartDate', $_GET['start_date'] );
}
$start_day = new w2p_Utilities_Date( $AppUI->getState( 'TimecardStartDate' ) ? $AppUI->getState( 'TimecardStartDate' ) : NULL);

//set the time to noon to combat a php date() function bug that was adding an hour.
$date = $start_day->format("%Y-%m-%d")." 12:00:00";
$start_day -> setDate($date, DATE_FORMAT_ISO);

$today_weekday = $start_day->getDayOfWeek();

//roll back to the first day of that week, regardless of what day was specified
$rollover_day = '0';
$new_start_offset = $rollover_day - $today_weekday;
$start_day -> addDays($new_start_offset);

//last day of that week, add 6 days
$end_day = $start_day->duplicate();
$end_day -> addDays(6);

//date of the first day of the previous week.
$prev_date = $start_day->duplicate();
$prev_date -> addDays(-7);

//date of the first day of the next week.
$next_date = $start_day->duplicate();
$next_date -> addDays(7);

$is_my_timesheet = ($user_id == $AppUI->user_id || $can_edit_other_timesheets);

$tl = $AppUI->getPref('TASKLOGEMAIL');
$ta = $tl & 1;
$tt = $tl & 2;
$tp = $tl & 4;
?>
<style type="text/css">
tr.dayheader td {
    background-color:#D7EAFF;
}
tr.workday {
}
td.holiday {
    color: brown !important;
}
span.remaininghours {
    padding-left: 5px;
    padding-right:5px;
    border:2px solid red;
    background-color:#FFF2F2;
    font-weight: bold;
}
span.workhours {
    padding-left: 5px;
    padding-right:5px;
    border:2px solid #999999;
    background-color:#FFF2F2;
    font-weight: bold;
}
#task-log-form td {
    padding: 0;
}
#task-log-form textarea {
    height: 100%;
    width: 95%;
    margin: 0;
}
</style>
<form name="user_select" method="get">
<input type="hidden" name="m" value="timecard" />
<input type="hidden" name="tab" value="0" />
<input type="hidden" name="start_date" value="<?php echo $start_day->getDate();?>" />
<table align="center">
    <tr>
        <td nowrap="nowrap">
            <input type="checkbox" name="log_ignore" value="1" onChange="document.user_select.submit();" <?php echo ($log_ignore) ? 'checked="checked"' : ''?>/> <?php echo $AppUI->_( 'Ignore 0 hours' );?>
        </td> 
        <td>
            <a href="?m=timecard&amp;user_id=<?php echo $user_id;?>&amp;start_date=<?php echo urlencode($prev_date->getDate()) ;?>">
                <img src="<?php echo w2PfindImage('prev.gif'); ?>" width="16" height="16" alt="<?php echo $AppUI->_( 'previous' );?>" border="0" />
            </a>
        </td>
        <td>
            <b><?php echo $start_day -> getDayName(false). " " .$start_day->format( $df ); ?></b>
            <?php echo " " . $AppUI->_('through'). " ";?>
            <b><?php echo $end_day -> getDayName(false). " " .$end_day->format( $df ); ?></b>
        </td>
        <td>
            <a href="?m=timecard&amp;user_id=<?php echo $user_id;?>&amp;start_date=<?php echo urlencode($next_date->getDate()); ?>">
                <img src="<?php echo w2PfindImage('next.gif'); ?>" width="16" height="16" alt="<?php echo $AppUI->_( 'next' );?>" border="0" />
            </a>
        </td>
        <?php if($show_other_worksheets){ ?>
        <td align="right">
            <select name="user_id" onChange="document.user_select.submit();">
            <?php
            $perms = &$AppUI->acl();
            $users = $perms->getPermittedUsers('tasks');
            foreach ($users as $user_key => $user_name) {
                $selected = ($user_key == $user_id) ? ' selected="selected"' : '';
                ?><option value="<?php echo $user_key; ?>"<?php echo $selected; ?>><?php echo $user_name; ?></option><?php
            } ?>
            </select>
        </td>
        <td align="left" nowrap="nowrap"><a href="?m=timecard&amp;tab=0&amp;user_id=<?php echo $AppUI->user_id; ?>"><?php echo '['.$AppUI->_('My Time Card').']';?></a></td>
        <?php } ?>
    </tr>
</table>
</form>
<form id="addedit_task_log" action="" name="addedit_task_log" method="post" onsubmit="return false;" accept-charset="utf-8">
<input type="hidden" name="tab" value="<?php echo $newTLogTabNum; ?>" />
<input type="hidden" name="dosql" value="do_tasklog_aed">
<input type="hidden" name="del" value="0">
<input type="hidden" name="task_log_id" value="0" />
<input type="hidden" name="task_log_record_creator" value="<?php echo $user_id; ?>" />
<input type="hidden" name="task_log_creator" value="0" />
<input type="hidden" name="task_log_date" value="" />
<input type="hidden" name="task_log_name" value="" />
<input type="hidden" name="task_log_reference" value="0" />
<input type="hidden" name="task_log_costcode" value="" />
<input type="hidden" name="task_log_problem" value="0" />
<table width="100%" border="0" cellpadding="2" cellspacing="1" class="tbl">
    <tr>
        <?php if($helpdesk_available){ ?>
        <th width="9%"><?php echo $AppUI->_('Task Name or Helpdesk Item'); ?></th>
        <?php } else { ?>
        <th width="9%"><?php echo $AppUI->_('Task Name'); ?></th>
        <?php } ?>
        <th width="1%"><?php echo $AppUI->_('%'); ?></th>
        <th width="75%"><?php echo $AppUI->_('Log Entry'); ?></th>
        <th width="12%"><?php echo $AppUI->_('Worked Hours'); ?></th>
        <th width="3%" colspan="2">&nbsp;</th>
    </tr>
    <?php
    //set the time the beginning of the first day and end of the last day.
    $date = $start_day->format("%Y-%m-%d")." 00:00:00";
    $start_day->setDate($date, DATE_FORMAT_ISO);
    $date = $end_day->format("%Y-%m-%d")." 23:59:59";
    $end_day->setDate($date, DATE_FORMAT_ISO);
    //12:23 AM 2007-08-06 Query modified to use task name rather than task summary
    $q = new w2p_Database_Query; 
    $q->addQuery('task_log.*, tasks.task_id, tasks.task_name, tasks.task_percent_complete, projects.project_short_name');
    $q->addTable('task_log');
    $q->addJoin('tasks', '', 'task_log.task_log_task = tasks.task_id');
    $q->leftJoin('projects', '', 'projects.project_id = tasks.task_project');
    if ($helpdesk_available) {
        $q->addQuery('helpdesk_items.item_title, helpdesk_items.item_calltype');
        $q->addJoin('helpdesk_items', '', 'task_log.task_log_help_desk_id = helpdesk_items.item_id');
        $q->addWhere('(task_log_task > 0 OR task_log_help_desk_id > 0)');
    }  
    else {
        $q->addWhere('(task_log_task > 0)');
    }
    $q->addWhere('task_log_creator=' . $user_id. ' AND task_log_date >= \''. $start_day->format( FMT_DATETIME_MYSQL ) . '\' AND task_log_date <= \'' . $end_day->format( FMT_DATETIME_MYSQL ) . '\' ');
    if ($log_ignore) {  $q->addWhere('task_log.task_log_hours > 0'); }
    $q->addOrder('task_log_date');
    $tasklogs = $q->loadList();
    $date = $start_day->format("%Y-%m-%d")." 12:00:00";
    $start_day -> setDate($date, DATE_FORMAT_ISO);
    $total_hours_daily = 0;
    $total_hours_weekly = 0;
    $last_day = $start_day->duplicate();
    $holidays = getHolidaysList($start_day->duplicate(), $user_id);
    $cal_working_days = explode(",", w2PgetConfig("cal_working_days"));
    for($dow = 0; $dow < 7; $dow++){
        $isWorkingDay = !isset($holidays[$last_day->format(FMT_TIMESTAMP_DATE)]);
        if (!$show_only_calendar_working_days || in_array($last_day->getDayOfWeek(), $cal_working_days)) {
            $day_name = $last_day->getDayName(false);
            if(!$isWorkingDay) {
                $total_hours_daily += $min_hours_day;
            } else {
                foreach ($tasklogs as $t => $tasklog) {
                    $task_date = new w2p_Utilities_Date( $tasklog["task_log_date"] );
                    $task_dow = $task_date->getDayOfWeek();
                    $tasklogs[$t]["task_day_of_week"] = $task_dow;
                    if($task_dow == $dow){
                        $total_hours_daily += $tasklog["task_log_hours"];
                    }
                }
            }
            ?>
            <tr class="dayheader">
                <td nowrap="nowrap" valign="top" colspan="6">
                    <div align="left">
                        <b><?php echo $day_name; ?></b> <?php echo $last_day->format( $df ); ?>
                    </div>
                </td>
            </tr>
            <?php if(!in_array($last_day->getDayOfWeek(), $cal_working_days)){ ?>
            <tr>
                <td class="holiday" nowrap="nowrap" valign="top"><?php echo $AppUI->_('Nonworking day'); ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php
            } elseif(!$isWorkingDay){ ?>
            <tr>
                <td class="holiday" nowrap="nowrap" valign="top">
                    <?php $title = $holidays[$last_day->format(FMT_TIMESTAMP_DATE)]; echo gettype($title) == 'object' ? $title->getMessage() : $title; ?>
                </td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td align="right" valign="top"><?php echo logToHours($min_hours_day); ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php
            }
            else {
                foreach ($tasklogs as $tasklog) {
                    if($tasklog["task_day_of_week"] == $dow){ ?>
                        <tr class="workday">
                            <td nowrap="nowrap" valign="top">
                            <?php if($tasklog['task_log_task']){ ?>
                                <a href="?m=tasks&amp;a=view&amp;task_id=<?php echo $tasklog["task_id"]; ?>">
                                    <?php 
                                    if ($tasklog["project_short_name"]) echo $tasklog["project_short_name"].'::'; 
                                    echo $tasklog["task_name"]; 
                                    ?>
                                </a>
                            <?php } elseif ($tasklog['task_log_help_desk_id']){ ?>
                                <a href="?m=helpdesk&amp;a=view&amp;item_id=<?php echo $tasklog["task_log_help_desk_id"];?>"><?php echo w2PshowImage ('ct'.$tasklog['item_calltype'].'.png', 10, 13, 'align=center','', 'helpdesk' ).$tasklog["task_log_help_desk_id"].'::'.$tasklog["item_title"]; ?></a>
                            <?php } else { ?>
                                <?php echo ($tasklog["task_log_name"]); ?>
                            <?php } ?>
                            </td>
                            <td><?php echo ($tasklog["task_percent_complete"]); ?>%</td>
                            <td>
                                <?php echo $tasklog["task_log_description"]; ?>
                            </td>
                            <td align="right" valign="top"><?php echo logToHours($tasklog["task_log_hours"]); ?></td>
                            <?php if (($tasklog['task_log_creator']==$AppUI->user_id || $can_edit_other_timesheets) &&  
                                            (!isset($tasklog['task_log_help_desk_id']) || (isset($tasklog['task_log_help_desk_id']) && !$tasklog['task_log_help_desk_id']) || $helpdesk_available)) { ?>
                            <td><?php echo w2PtoolTip('edit task log', 'click to edit this task log'); ?><a class="edit-log-link" href="javascript: void(0);" id="edit<?php echo $tasklog["task_log_id"]; ?>"><?php echo w2PshowImage('icons/pencil.gif', 12, 12); ?></a><?php echo w2PendTip(); ?></td>
                            <td><?php echo w2PtoolTip('delete task log', 'click to delete this task log'); ?><a class="del-log-link" href="javascript: void(0);" id="del<?php echo $tasklog["task_log_id"]; ?>"><?php echo w2PshowImage('icons/stock_delete-16.png', 12, 12); ?></a><?php echo w2PendTip(); ?></td>
                            <?php } else { ?>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <?php } ?>
                        </tr>
                        <?php
                    }
                }
            }
            if (in_array($last_day->getDayOfWeek(), $cal_working_days)) { ?>
            <tr>
                <td>
                <?php if ($isWorkingDay && $is_my_timesheet) { ?>
                    <a id="<?php echo $last_day->getDate(); ?>" class="add-log-link" href="javascript: void(0);"><?php echo w2PtoolTip('Add Log', 'create a new log record for this day') . w2PshowImage('edit_add.png') . w2PendTip(); ?></a>
                <?php } ?>
                </td>
                <td>&nbsp;</td>
                <td align="right"><b><?php echo sprintf($AppUI->_('Total %s'), $day_name); ?></b></td>
                <td align="right">
                <?php if($show_possible_hours_worked) { ?>
                    <?php if($total_hours_daily < $min_hours_day && in_array($last_day->getDayOfWeek(), $cal_working_days)){ ?>
                    <span id="rh<?php echo substr($last_day->getDate(), 0, 10); ?>" class="remaininghours"><?php echo substractTimes(logToHours($min_hours_day), logToHours($total_hours_daily)); ?></span>
                    <?php } else { ?>
                    <span id="rh<?php echo substr($last_day->getDate(), 0, 10); ?>" class="remaininghours" style="display: none;"></span>
                    <?php } ?>
                <?php } ?>
                <?php if(in_array($last_day->getDayOfWeek(), $cal_working_days)){ ?>
                    <span id="wh<?php echo substr($last_day->getDate(), 0, 10); ?>" class="workhours"><?php echo logToHours($total_hours_daily); ?></span>
                <?php } ?>
                </td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php
            }
            $total_hours_weekly += $total_hours_daily;
            $total_hours_daily = 0;
        }
        $last_day->addDays(1);
        $date = $last_day->format("%Y-%m-%d")." 12:00:00";
        $last_day -> setDate($date, DATE_FORMAT_ISO);
    }
    $tasks = getTodoTasks($user_id);
    ?>
    <tr>
        <th nowrap="nowrap" valign="top" colspan="3">
            <div align="left">
                <b><?php echo sprintf($AppUI->_('Activity report for the week of %1$s through %2$s'), $start_day -> getDayName(false). " " .$start_day->format( $df ), $end_day -> getDayName(false). " " .$end_day->format( $df ))?></b>
            </div>
        </th>
        <th align="right">
        <?php if($show_possible_hours_worked) { ?>
            <?php if ($total_hours_weekly<$min_hours_week) { ?>
            <span id="trh" class="remaininghours"><?php echo substractTimes(logToHours($min_hours_week), logToHours($total_hours_weekly)); ?></span>
            <?php } else { ?>
            <span id="trh" class="remaininghours" style="display:none;"></span>
            <?php } ?>
        <?php } ?>
            <span id="twh" class="workhours"><?php echo logToHours($total_hours_weekly); ?></span>
        </th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
    </tr>
    <tr id="task-log-form" style="display:none; padding: 0;">
        <td><?php echo makeTodoTasksList($user_id, $tasks);?></td>
        <td><?php echo arraySelect($percent, 'task_percent_complete', 'size="1"', 0);?></td>
        <td>
            <textarea class="text" name="task_log_description" rows="1"></textarea>
            &nbsp;<?php echo w2PtoolTip('other options', 'click here to edit other available options.'); ?><a id="task-log-expand" href="javascript: "><img src="<?php echo w2PfindImage('icons/expand.gif', $m); ?>" width="12" height="12" border="0" /></a><a id="task-log-collapse" href="javascript: " style="display:none"><img src="<?php echo w2PfindImage('icons/collapse.gif', $m); ?>" width="12" height="12" border="0"></a><?php echo w2PendTip(); ?>
        </td>
        <td align="right"><input type="text" class="text" style="width:70%;text-align:right;font-size:0.9em" name="task_log_hours" value="" /></td>
        <td><?php echo w2PtoolTip('save task log', 'click to save this task log'); ?><a class="task_log_submit" href="javascript: void(0);"><?php echo w2PshowImage('icons/stock_ok-16.png', 16, 16); ?></a><?php echo w2PendTip(); ?></td>
        <td><?php echo w2PtoolTip('Cancel', 'click to cancel'); ?><a class="task_log_cancel" href="javascript: void(0);"><?php echo w2PshowImage('icons/stock_cancel-16.png', 16, 16); ?></a><?php echo w2PendTip(); ?></td>
    </tr>
</table>
</form>
<script type="text/javascript">
var total_hours_weekly = <?php echo $total_hours_weekly; ?>;
var min_hours_day = <?php echo $min_hours_day; ?>;
var min_hours_week = <?php echo $min_hours_week; ?>;
var tasks = [];
var tasklogs = [];
<?php 
foreach ($tasks as $task) {
    echo "tasks['".$task['task_id']."'] = " . json_encode($task).";\n"; 
}
foreach ($tasklogs as $tasklog) {
    echo "tasklogs['".$tasklog['task_log_id']."'] = " . json_encode($tasklog).";\n"; 
}
?>
var hiddenTaskLog = null;
function newTaskLogRow(tasklog) {
    var row = '<tr class="workday">';
    row += '<td nowrap="nowrap" valign="top">';
    if(tasklog.task_log_task){ 
        row += '<a href="?m=tasks&amp;a=view&amp;task_id='+tasklog.task_id+'">';
        if (tasklog.project_short_name) row += tasklog.project_short_name+'::'; 
        row += tasklog.task_name; 
        row += '</a>';
    } else if (tasklog.task_log_help_desk_id){
        row += '<a href="?m=helpdesk&amp;a=view&amp;item_id='+tasklog.task_log_help_desk_id+'"><img src="ct'+tasklog.item_calltype+'.png" width="10" height="13" align="center">'+tasklog.task_log_help_desk_id+'::'+tasklog.item_title+'</a>';
    } else {
        row += tasklog.task_log_name;
    }
    row += '</td>';
    row += '<td>'+tasklog.task_percent_complete+'%</td>';
    row += '<td>'+tasklog.task_log_description+'</td>';
    row += '<td align="right" valign="top">'+logToHours(parseFloat(tasklog.task_log_hours) * 60)+'</td>';
    row += '<td><?php echo w2PtoolTip("edit task log", "click to edit this task log"); ?><a class="edit-log-link" href="javascript: void(0);" id="edit'+tasklog.task_log_id+'"><?php echo w2PshowImage("icons/pencil.gif", 12, 12); ?></a><?php echo w2PendTip(); ?></td>';
    row += '<td><?php echo w2PtoolTip("delete task log", "click to delete this task log"); ?><a class="del-log-link" href="javascript: void(0);" id="del'+tasklog.task_log_id+'"><?php echo w2PshowImage("icons/stock_delete-16.png", 12, 12); ?></a><?php echo w2PendTip(); ?></td>';
    row += '</tr>';
    return row;
}
function logToHours(value) {
    var hours = Math.floor(value/60) + "";
    var minutes = Math.round(value%60) + "";
    if (minutes < 10) minutes = '0'+minutes;
    return hours+":"+minutes;
}
function hoursToLog(hours) {
    var hm = hours.split(":");
    var minutes = parseInt(hm[0]) * 60 + parseInt(hm[1]);
    return (minutes / 60);
}
function substractTimes(time1, time2) {
    var minutes = 0;
    var hm = time1.split(":");
    var minutes = parseInt(hm[0]) * 60 + parseInt(hm[1]);
    hm = time2.split(":");
    minutes -= parseInt(hm[0]) * 60 + parseInt(hm[1]);
    if (minutes <= 0) return "";
    var hours = Math.floor(minutes/60);
    minutes -= hours * 60;
    if (minutes < 10) minutes = '0'+minutes;
    return hours+":"+minutes;
}
function sumTimes() {
    minutes = 0;
    for (t = 0; t < arguments.length; t++) {
        var hm = arguments[t].split(":");
        if (hm[0].charAt(0) == '-') {
            minutes -= (parseInt(hm[0].substr(1))) * 60 + parseInt(hm[1]);
        }
        else {
            minutes += (parseInt(hm[0])) * 60 + parseInt(hm[1]);
        }
    }
    var hours = Math.floor(minutes/60);
    minutes -= hours * 60;
    if (minutes < 10) minutes = '0'+minutes;
    return hours+":"+minutes;
}
function submitTasklog(form, success, error) {
    var params = (form) ? form.serializeArray() : [];
    var result = true;
    $("#loadingMessage").show();
    $.ajax(
        {
            url: "index.php?m=timecard&suppressHeaders=1",
            type: "POST",
            data: params,
            dataType: "json",
            success: success,
            error: error,
            complete: function() {
                $("#loadingMessage").hide();
            }
        }
    );
    return result;
}
function deleteTasklog(form, success, error) {
    var params = (form) ? form.serializeArray() : [];
    var result = true;
    $("#loadingMessage").show();
    $.ajax(
        {
            url: "index.php?m=timecard&suppressHeaders=1",
            type: "POST",
            data: params,
            success: success,
            error: error,
            complete: function() {
                $("#loadingMessage").hide();
            }
        }
    );
    return result;
}
function updateTotalHours(log_hours, date) {
    total_hours_weekly += log_hours;
    var task_log_hours = log_hours < 0 ? "-"+ logToHours(Math.abs(log_hours) * 60) : logToHours(log_hours * 60);
    var workinghours = $('#wh'+date).text();
    $('#wh'+date).text(sumTimes(workinghours, task_log_hours));
    var remaininghours = substractTimes(logToHours(min_hours_day * 60), $('#wh'+date).text());
    if (remaininghours) {
        $('#rh'+date).text(remaininghours);
        $('#rh'+date).show();
    }
    else {
        $('#rh'+date).hide();
    }
    $('#twh').text(logToHours(total_hours_weekly * 60));
    <?php if($show_possible_hours_worked) { ?>
    if (total_hours_weekly < min_hours_week) {
        $('#trh').text(logToHours((min_hours_week - total_hours_weekly) * 60));
        $('#trh').show();
    }
    else {
        $('#trh').hide();
    }
    <?php } ?>
}
function submitIt() {
    if ($("textarea[name=task_log_description]").val().length<1) {
        alert( "<?php echo $AppUI->_('Please enter a worthwhile comment.', UI_OUTPUT_JS); ?>" );
        $("textarea[name=task_log_description]").focus();
    }
    else if ($("select[name=task_log_task]").val() == 0){
        alert( "<?php echo $AppUI->_('You must select a Task.', UI_OUTPUT_JS); ?>" );
        $("select[name=task_log_task]").focus();
    }
    else {
        $("input[name=task_log_hours]").val(hoursToLog($("input[name=task_log_hours]").val()));
        submitTasklog($('#addedit_task_log'),
            function(msg, textStatus){
                if (/^Error:/.test(msg)) {
                    alert(msg);
                }
                else {
                    old_log_hours = tasklogs[msg.task_log_id]? tasklogs[msg.task_log_id].task_log_hours : 0;
                    tasklogs[msg.task_log_id] = msg;
                    log_hours = parseFloat(msg.task_log_hours) - parseFloat(old_log_hours);
                    var date = msg.task_log_date.substr(0, 10);
                    updateTotalHours(log_hours, date);
                    if (hiddenTaskLog) {
                        hiddenTaskLog.replaceWith(newTaskLogRow(msg));
                        hiddenTaskLog.show();
                        hiddenTaskLog = null;
                    }
                    else {
                        $("#task-log-form").after(newTaskLogRow(msg));
                    }
                    $("#task-log-form").hide();
                    $("span").tipTip({maxWidth: "auto", delay: 200, fadeIn: 150, fadeOut: 150});
                }
            },
            function(msg, textStatus, errorThrown){
                alert(msg);
            }
        );
    }
}
$('<link>').attr({ 'rel': 'stylesheet', 'href': 'modules/timecard/lib/jquery-ui/jquery.ui.theme.css' }).appendTo('head')
$('<link>').attr({ 'rel': 'stylesheet', 'href': 'modules/timecard/lib/jquery-ui/ui-spinner/jquery.ui.button.css' }).appendTo('head')
$('<link>').attr({ 'rel': 'stylesheet', 'href': 'modules/timecard/lib/jquery-ui/ui-spinner/jquery.ui.spinner.css' }).appendTo('head')
$.getScript ("modules/timecard/lib/jquery-ui/jquery.ui.core.min.js", function () {
    $.getScript ("modules/timecard/lib/jquery-ui/jquery.ui.widget.min.js", function () {
        $.getScript ("modules/timecard/lib/jquery-ui/jquery.mousewheel.min.js", function () {
            $.getScript ("modules/timecard/lib/jquery-ui/jquery.ui.button.min.js", function () {
                $.getScript ("modules/timecard/lib/jquery-ui/jquery.textarea-expander.js", function () {
                    $.getScript ("modules/timecard/lib/jquery-ui/ui-spinner/jquery-glob/jquery.glob.min.js", function () {
                        $.getScript ("modules/timecard/lib/jquery-ui/ui-spinner/jquery-glob/globinfo/jquery.glob.all.min.js", function () {
                            $.getScript ("modules/timecard/lib/jquery-ui/ui-spinner/jquery.ui.spinner.js", function () {
                            $(document).ready(function() {
                                $(".add-log-link").click(function () {
                                    if (hiddenTaskLog) {
                                        hiddenTaskLog.show();
                                        hiddenTaskLog = null;
                                    }
                                    $("input[name=task_log_id]").val(0);
                                    $("input[name=task_log_date]").val($(this).attr('id'));
                                    $("input[name=task_log_creator]").val(<?php echo $user_id; ?>);
                                    $(this).parent().parent().before($("#task-log-form"));
                                    $("form[name=addedit_task_log]").get(0).reset();
                                    $("input[name=task_log_hours]").val('0:10');
                                    $("#task-log-form textarea").css("height", "100%");
                                    $("#task-log-form").show();
                                });
                                $(".edit-log-link").live('click', function () {
                                    if (hiddenTaskLog) {
                                        hiddenTaskLog.show();
                                        hiddenTaskLog = null;
                                    }
                                    var tasklogid = $(this).attr('id').substr(4);
                                    var tasklog = tasklogs[tasklogid];
                                    $("input[name=task_log_id]").val(tasklogid);
                                    $("input[name=task_log_date]").val(tasklog['task_log_date']);
                                    $("input[name=task_log_creator]").val(tasklog['task_log_creator']);
                                    var taskId = tasklog['task_id'];
                                    $("input[name=task_percent_complete]").val(tasklog['task_percent_complete']);
                                    $("input[name=task_log_name]").val(tasklog['task_log_name']);
                                    $("textarea[name=task_log_description]").val(tasklog['task_log_description']);
                                    $("select[name=task_log_task]").val(tasklog['task_log_task']);
                                    $("select[name=task_percent_complete]").val(tasklog['task_percent_complete']);
                                    $("input[name=task_log_hours]").val(logToHours(parseFloat(tasklog['task_log_hours']) * 60));
                                    $(this).parent().parent().parent().before($("#task-log-form"));
                                    $("#task-log-form").show();
                                    hiddenTaskLog = $(this).parent().parent().parent();
                                    hiddenTaskLog.hide();
                                });
                                $(".del-log-link").live('click', function () {
                                    var tasklogid = $(this).attr('id').substr(3);
                                    var tasklog = tasklogs[tasklogid];
                                    if (confirm( "<?php echo $AppUI->_('Are you sure that you would like to delete this task log?', UI_OUTPUT_JS).'\n'; ?>" )) {
                                        $("input[name=del]").val(1);
                                        $("input[name=task_log_id]").val(tasklogid);
                                        $row = $(this).parent().parent().parent();
                                        deleteTasklog($('#addedit_task_log'),
                                            function(msg, textStatus){
                                                if (/^Error:/.test(msg)) {
                                                    alert(msg);
                                                }
                                                else {
                                                    log_hours = -parseFloat(tasklog['task_log_hours']);
                                                    date = tasklog['task_log_date'];
                                                    updateTotalHours(log_hours, date);
                                                    $row.remove();
                                                }
                                            },
                                            function(msg, textStatus, errorThrown){
                                                alert(msg);
                                            }
                                        );
                                    }
                                });
                                $.preferCulture('fr');
                                $("input[name=task_log_hours]").spinner({
                                    value: '0:10',
                                    step: 10,
                                    page: 60,
                                    min: 10,
                                    numberformat: 'C',
                                    max: <?php echo $min_hours_day; ?> * 60,
                                    parse: function(value) {
                                        if (typeof value == 'string') {
                                            var minsec = value.split(':');
                                            value = parseInt(minsec[0]) * 60 + parseInt(minsec[1]);
                                        }
                                        return value;
                                    },
                                    format: function(value) {
                                        return logToHours(value);
                                    }
                                });
                                $("textarea").TextAreaExpander(16);
                                $("a.task_log_cancel").click(function () {
                                    if (hiddenTaskLog) {
                                        hiddenTaskLog.show();
                                        hiddenTaskLog = null;
                                    }
                                    $("#task-log-form").hide();
                                });
                                $("a.task_log_submit").click(function () {
                                    submitIt();
                                });
                                $("input[name=task_log_hours]").keyup(function (e) {
                                    e.preventDefault();
                                    if (e.which == 13) submitIt();
                                });
                                $("#task-log-expand").click(function () {
                                    $("input[name=task_log_hours]").val(hoursToLog($("input[name=task_log_hours]").val()));
                                    $("#addedit_task_log").attr("action", "?m=timecard&tab=<?php echo $newTLogTabNum; ?>");
                                    $("input[name=dosql]").remove();
                                    $("input[name=tab]").remove();
                                    document.addedit_task_log.submit();
                                });
                                $("#task_log_task").change(function () {
                                    var taskId = $(this).val();
                                    var task = tasks[taskId];
                                    $("input[name=task_log_name]").val(task['task_name']);
                                    $("select[name=task_percent_complete]").val(task['task_percent_complete']);
                                });
                            });
                            });
                        });
                    });
                });
            });
        });
    });
});
</script>
<?php
function logToHours($value) {
    $value = $value * 60;
    $hours = floor($value/60)."";
    $minutes = round($value%60)."";
    $minutes = ((strlen($minutes) == 1) ? ('0' . $minutes) : $minutes);
    return $hours . ':' . $minutes;
}
function substractTimes($time1, $time2) {
    $minutes = 0;
    list($hour, $minute) = explode(':', $time1);
    $minutes += ((int)$hour) * 60;
    $minutes += (int)$minute;
    list($hour, $minute) = explode(':', $time2);
    $minutes -= ((int)$hour) *60;
    $minutes -= (int)$minute;
    $hours = floor($minutes/60);
    $minutes -= $hours*60;
    $minutes = ((strlen($minutes."") == 1) ? ('0' . $minutes) : $minutes."");
    return "{$hours}:{$minutes}";
}
function sumTimes() {
    $times = func_get_args();
    $minutes = 0;
    foreach ($times as $time) {
        list($hour,$minute) = explode(':', $time);
        if ($hour{0} == '-') {
            $minutes -= ((int)substr($hour, 1)) * 60 + (int)$minute;
        }
        else {
            $minutes += ((int)$hour) * 60 + (int)$minute;
        }
    }
    $hours = floor($minutes/60);
    $minutes -= $hours*60;
    $minutes = ((strlen($minutes."") == 1) ? ('0' . $minutes) : $minutes."");
    return "{$hours}:{$minutes}";
}
function getTodoTasks($user_id) {
    // get user -> tasks
    $q = new w2p_Database_Query; 
    $q->addQuery('u.task_id, t.task_name, t.task_parent, t.task_percent_complete, t.task_project, p.project_name, p.project_short_name, p.project_company, p.project_color_identifier, c.company_name');
    $q->addTable('user_tasks','u');
    $q->addTable('tasks','t');
    $q->addJoin('projects','p','p.project_id = t.task_project');
    $q->addJoin('companies','c','c.company_id = p.project_company');
    $q->addWhere('u.user_id = ' . $user_id .'');
    $q->addWhere('u.task_id = t.task_id');
    $q->addWhere('t.task_dynamic <> 1'); // see mantis bug 0000705
    $q->addWhere('( t.task_percent_complete < 100 or t.task_percent_complete is null)');
    $q->addWhere('t.task_status = 0');
    $q->addWhere('p.project_active > 0');
    if (($template_status = w2PgetConfig('template_projects_status_id')) != '') {
        $q->addWhere('p.project_status <> ' . $template_status);
    }
    $q->addOrder('p.project_name, t.task_parent, t.task_name');
    $tasks = $q->loadList();
    foreach ($tasks as $t => $task) {
        $q->addTable('task_contacts', 'tc');
        $q->addJoin('contacts', 'c', 'c.contact_id = tc.contact_id', 'inner');
        $q->addWhere('tc.task_id = ' . (int)$task['task_id']);
        $q->addQuery('tc.contact_id');
        $q->addQuery('c.contact_first_name, c.contact_last_name');
        $req = &$q->exec();
        $cidtc = array();
        $task_email_title = array();
        for ($req; !$req->EOF; $req->MoveNext()) {
            $cidtc[] = $req->fields['contact_id'];
            $task_email_title[] = $req->fields['contact_first_name'] . ' ' . $req->fields['contact_last_name'];
        }
        $tasks[$t]['task_contact_id'] = $cidtc;
        $tasks[$t]['task_email_title'] = $task_email_title;
        $q->clear();
        $q->addTable('project_contacts', 'pc');
        $q->addJoin('contacts', 'c', 'c.contact_id = pc.contact_id', 'inner');
        $q->addWhere('pc.project_id = ' . (int)$task['task_project']);
        $q->addQuery('pc.contact_id');
        $q->addQuery('c.contact_first_name, c.contact_last_name');
        $req = &$q->exec();
        $cidpc = array();
        $proj_email_title = array();
        for ($req; !$req->EOF; $req->MoveNext()) {
            if (!in_array($req->fields['contact_id'], $cidpc)) {
                $cidpc[] = $req->fields['contact_id'];
                $proj_email_title[] = $req->fields['contact_first_name'] . ' ' . $req->fields['contact_last_name'];
            }
        }
        $tasks[$t]['project_contact_id'] = $cidpc;
        $tasks[$t]['project_email_title'] = $proj_email_title;
        $q->clear();
    }
    return $tasks;
}
function makeTodoTasksList($user_id, $tasks = null) {
    global $AppUI;
    if (is_null($tasks)) {
        $tasks = getTodoTasks($user_id);
    }
    $todos = '<select name="task_log_task" id="task_log_task">'."\n";
    $todos.= '<option value="0">---'.$AppUI->_('Select a task').'---</option>'."\n";
    $project_name = "";
    foreach ($tasks as $task) {
        if ($task['project_name'] != $project_name) {
            if ($project_name) {
                $todos.= '</optgroup>'."\n";
            }
            $project_name = $task['project_name'];
            $todos .= '<optgroup label="'.$project_name.'" style="background-color:#'.$task['project_color_identifier'].'; color:'.bestColor($task['project_color_identifier']).'">'."\n";
        }
        $todos .= '<option style="background-color: white; color:black;" value="'.$task['task_id'].'">'.$task['project_short_name'].'::'.$task['task_name'].'</option>';
    }
    if ($project_name) {
        $todos.= '</optgroup>'."\n";
        $todos.= '</select>'."\n";
    }
    return $todos;
}
function getBillingCodes() {
    global $AppUI;
    $q = new w2p_Database_Query();
    $q->addTable('billingcode');
    $q->addQuery('billingcode_id, billingcode_name');
    $q->addWhere('billingcode_status=0');
    $q->addWhere('(company_id=' . $AppUI->user_company . ' OR company_id = 0 )');
    $q->addOrder('billingcode_name');
    $rows = $q->loadList();
    $billingCodes = array('0' => "None");
    if ($rows){
        foreach($rows as $row){
            $billingCodes[$row["billingcode_id"]] = $row["billingcode_name"];
        }
    }
    return $billingCodes;
}

function getHolidaysList($start_day, $user_id) {
    global $AppUI;
    $holidays = array();
    if ($AppUI->isActiveModule('holiday')) {
        require_once W2P_BASE_DIR."/modules/holiday/holiday_functions.class.php";
        $end_day = $start_day->duplicate();
        $end_day -> addDays(6);
        $holidaysList = HolidayFunctions::getHolidaysForDatespan( $start_day, $end_day, $user_id );
        foreach($holidaysList as $holiday) {
            $id = 0;
            $type = $holiday['type'];
            $description = $holiday['description'] ? $holiday['description'] : "";
            $name = $holiday['name'] ? $holiday['name'] : "";
            $odate = $holiday['startDate'];
            $oend = $holiday['endDate'];
            $cdate = clone $odate;
            while (!$cdate->after(clone $oend)) {
                $holidays[$odate->format(FMT_TIMESTAMP_DATE)] = $description;
                $odate = $odate->getNextDay();
                $cdate = clone $odate;
            }
        }
    }
    else {
        for($dow = 0; $dow < 7; $dow++){
            if (!$start_day->isWorkingDay()) {
                $holidays[$start_day->format(FMT_TIMESTAMP_DATE)] = "";
            }
            $start_day->addDays(1);
        }
    }
    return $holidays;
}