<?php
/**
* Adapted for Web2project by MiraKlim
* Adapted for Web2project 2.1 by Eureka and colleagues from his company (DILA)
* by kirkawolff : Fix new timecard entry, where nested subtasks did not show nesting info and were not ordered based on tree for task selection.
**/
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}

global $AppUI, $task_id, $sf, $df, $canEdit, $m;

$AppUI->loadCalendarJS();

// format dates
$df = $AppUI->getPref('SHDATEFORMAT');

$perms = &$AppUI->acl();
if (!canView('task_log')) {
	$AppUI->redirect(ACCESS_DENIED);
}

// by kirkawolff
// recursive function to sort tasks list menu
function ourTaskTreeSort(&$outarr, &$inarr, $depth, $id)
{
    foreach ($inarr as $e) {
        if (($depth == 0) and ($e['par_id'] == $e['id'])) {
            // a root task
            $outarr[] = $e;
            ourTaskTreeSort($outarr, $inarr, $depth+1, $e['id']);
        }
        else if (($depth != 0) and ($id == $e['par_id']) and ($e['par_id'] != $e['id'])) {
            // found a child, add and look for descendents
            $e['depth'] = $depth;
            $outarr[] = $e;
            ourTaskTreeSort($outarr, $inarr, $depth+1, $e['id']);
        }
    }
}

$tlid = (int)w2PgetParam( $_GET, 'tlid', 0 );
$tid = (int)w2PgetParam( $_POST, 'task_log_task', 0 );
//pull data 
// if we have a TLID, then we editing an existing row
$q = new w2p_Database_Query; 
if ($tlid) {
    $q->addQuery('task_log.*, task_id, project_name, task_name, task_project, task_percent_complete, project_company');
    $q->addTable('task_log');
    $q->addJoin('tasks','','task_id = task_log_task');
    $q->addJoin('projects','','project_id = task_project');
    $q->addWhere('task_log_id = '. $tlid);
    $task = $q-> loadHash();
} elseif ($tid) {
    $q->addQuery('task_id, project_name, task_name, task_project, task_percent_complete, project_company');
    $q->addTable('tasks');
    $q->addJoin('projects','','project_id = task_project');
    $q->addWhere('task_id = '. $tid);
    $task = $q-> loadHash();
    $task['task_log_id'] = 0;
    $task['task_log_task'] = $tid;
    $task['task_log_help_desk_id'] = 0;
    $task['task_percent_complete'] = w2PgetParam( $_POST, 'task_percent_complete', '' );
    $task['task_log_name'] = w2PgetParam( $_POST, 'task_log_name', '' );
    $task['task_log_description'] = w2PgetParam( $_POST, 'task_log_description', '' );
    $task['task_log_creator'] = w2PgetParam( $_POST, 'task_log_creator', 0 );
    $task['task_log_hours'] = w2PgetParam( $_POST, 'task_log_hours', '' );
    $task['task_log_date'] = w2PgetParam( $_POST, 'task_log_date', '' );
    $task['task_log_costcode'] = (int)w2PgetParam( $_POST, 'task_log_costcode', 0 );
    $task['task_log_problem'] = (int)w2PgetParam( $_POST, 'task_log_problem', 0 );
    $task['task_log_reference'] = (int)w2PgetParam( $_POST, 'task_log_reference', 0 );
    $task['task_log_related_url'] = null;
    $task['task_log_record_creator'] = (int)w2PgetParam( $_POST, 'task_log_record_creator', 0 );
} else {
    $task = array();
}
$canEditTask = $perms->checkModuleItem('tasks', 'edit', $task['task_id']);
$canViewTask = $perms->checkModuleItem('tasks', 'view', $task['task_id']);

$is_new_record = !$tlid;
$task_found = $task['project_company']!=FALSE;
$require_task_info = $is_new_record || $task_found;

global $TIMECARD_CONFIG;
//Prevent users from editing other ppls timecards.
$can_edit_other_timesheets = $TIMECARD_CONFIG['minimum_edit_level']>=$AppUI->user_type;
if (!$can_edit_other_timesheets) {
    if(isset($_GET['tlid']) && (( $task['task_log_creator'] != $AppUI->user_id) || (!isset($task['task_log_creator'])))) {
        $AppUI->redirect( "m=public&a=access_denied" );
    }
}

if (isset( $task['task_log_date'] )) {
    $log_date = new w2p_Utilities_Date( $task['task_log_date'] ); 
} else if (isset( $_GET['date'] )) {
    $log_date = new w2p_Utilities_Date($_GET['date']); 
} else {
    $log_date = new w2p_Utilities_Date();
}

$tasks = array();
$projects = array();
$companies = array( '0'=>'' );

if ($is_new_record) {
    // get user -> tasks
    $q = new w2p_Database_Query; 
    $q->addQuery('u.task_id, t.task_name, t.task_parent, t.task_project, p.project_name, p.project_company, c.company_name');
    $q->addTable('user_tasks','u');
    $q->addTable('tasks','t');
    $q->addJoin('projects','p','p.project_id = t.task_project');
    $q->addJoin('companies','c','c.company_id = p.project_company');
    $q->addWhere('u.user_id = ' . $AppUI->user_id .'    AND u.task_id = t.task_id    AND t.task_dynamic <> 1');
    $q->addOrder('p.project_name, t.task_parent, t.task_name');
    $res = $q->exec();
  
    while ($row = db_fetch_assoc( $res )) {
        // collect tasks data to sort and populate list menu
        $task_dat[] = array( "par_id" => $row['task_parent'], "id" =>$row['task_id'], "depth" => 0 );
        // just hash this based on task_id(minimize sorted data)
        $task_inf[$row['task_id']] = array( "proj" => $row['task_project'], "task" =>$row['task_name']); 
        // collect projects in js format
        $projects[] = "[".$row['project_company'].",".$row['task_project'].",'".addslashes($row['project_name'])."']";
        // collect companies in normal format
        $companies[$row['project_company']] = $row['company_name'];
    }
    // sort our task data so it is ordered nice(from roots down to leafs), add depth info to add indentation later.
    $sorted_task_dat = array();
    ourTaskTreeSort($sorted_task_dat, $task_dat, 0, 0);
    // finally collect tasks in js format used to make list
    foreach ($sorted_task_dat as $e) {
        $id = $e['id'];
        $proj1 = $task_inf[$id]['proj'];
        $task1 = $task_inf[$id]['task'];
        $sp = '';  // indent to show relation
        for ($i=0; $i<$e['depth']; $i++)
            $sp = $sp . "-";
            $tasks[] = "[" .$proj1. "," .$id. ",'".addslashes($sp.$task1)."']";
    }
} else {
    // need to add the entry for the task itself as that was not found
    $tasks[$task['task_log_task']] = "[{$task['task_project']}, {$task['task_log_task']}, '{$task['task_name']}']";
    // collect projects in js format
    $projects[$task['task_project']] = "[{$task['project_company']},{$task['task_project']}, '{$task['project_name']}']";
    // get the company name
    $q = new w2p_Database_Query; 
    $q->addQuery('company_name');
    $q->addTable('companies');
    $q->addWhere('company_id = ' . $task['project_company'] );
    // collect companies in normal format
    $companies[$task['project_company']] = $q->LoadResult();
}

$compid=0;
if ($task['project_company']) 
    $compid=$task['project_company'];

$q = new w2p_Database_Query();
$q->addTable('billingcode');
$q->addQuery('billingcode_id, billingcode_name');
$q->addWhere('billingcode_status=0');
$q->addWhere('(company_id=' . $compid .')');
$q->addOrder('billingcode_name');
$ptrc = $q->loadList();

$task_log_costcodes[0]="None";

//$nums = 0;
if ($ptrc){
    foreach($ptrc as $row){
        $task_log_costcodes[$row["billingcode_id"]] = $row["billingcode_name"];
    }
}

##
## Set up JavaScript arrays
##
$ua = $_SERVER['HTTP_USER_AGENT'];
$isMoz = strpos( $ua, 'Gecko' ) !== false;
$isOpera = strpos( $ua, 'Opera' ) !== false;
$taskLogReference = w2PgetSysVal('TaskLogReference');

$projects = array_unique( $projects );
reset( $projects );

$s = "\nvar tasks = new Array(".implode( ",\n", $tasks ).")";
$s .= "\nvar projects = new Array(".implode( ",\n", $projects ).")";

echo "<script language=\"javascript\">$s</script>";

?>

<script language="javascript">


function setDate( frm_name, f_date ) {
    fld_date = eval( 'document.' + frm_name + '.' + f_date );
    fld_real_date = eval( 'document.' + frm_name + '.' + 'task_' + f_date );
    if (fld_date.value.length>0) {
        if ((parseDate(fld_date.value))==null) {
            alert("<?php echo $AppUI->_('The Date/Time you typed does not match your prefered format, please retype.', UI_OUTPUT_JS); ?>");
            fld_real_date.value = '';
            fld_date.style.backgroundColor = 'red';
        } else {
            fld_real_date.value = formatDate(parseDate(fld_date.value), 'yyyyMMdd');
            fld_date.value = formatDate(parseDate(fld_date.value), '<?php echo $cal_sdf ?>');
            fld_date.style.backgroundColor = '';
      }
    } else {
        fld_real_date.value = '';
    }
}    

//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
// List Handling Functions

function addToList( list, text, value ) {
    $(list).append(
        $("<option></option>")
        .attr("value",value)
        .text(text)
    ); 
}

function changeList( listName, source, target ) {
    var f = document.AddEdit;
    var list = eval( 'f.'+listName );
    
// clear the options
    $(list).empty();
    addToList( list, '', 0 );
    for (var i=0, n = source.length; i < n; i++) {
        if( source[i][0] == target ) {
            addToList( list, source[i][2], source[i][1] );
        }
    }
    if (list.options.length == 2)
        $(list).find("option:eq(1)").attr("selected", "selected");
    $(list).trigger("change");
}

// select an item in the list by target value
function selectList( listName, target ) {
    var f = document.AddEdit;
    var list = eval( 'f.'+listName );

    for (var i=0, n = list.options.length; i < n; i++) {
        if( list.options[i].value == target ) {
            list.options.selectedIndex = i;
            return;
        }
    }
}

//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

function submitIt() {
    var f = document.AddEdit;
     var chours = parseFloat( f.task_log_hours.value );
    if ((f.task_log_task) && (f.task_log_task.options.length>0) && (f.task_log_name.value.length<1)) {
        f.task_log_name.value = f.task_log_task.options[f.task_log_task.selectedIndex].text;
    }
    if (f.task_log_hours.value.length < 1) {
        alert( "<?php echo $AppUI->_('Please enter hours worked', UI_OUTPUT_JS); ?>" );
        f.task_log_hours.focus();
    } else if (chours > 24) {
        alert( "<?php echo $AppUI->_('Hours cannot exceed 24', UI_OUTPUT_JS); ?>" );
        f.task_log_hours.focus();
    } else if (f.task_log_description.value.length<1) {
        alert( "<?php echo $AppUI->_('Please enter a worthwhile comment.', UI_OUTPUT_JS); ?>" );
        f.task_log_description.focus();
    } else if ((f.project_company.options[f.project_company.selectedIndex].value==0) && (f.require_task_info.value=="true")){
        alert( "<?php echo $AppUI->_('You must select a Company.', UI_OUTPUT_JS); ?>" );
        f.project_company.focus();
    } else if ((f.task_project.options[f.task_project.selectedIndex].value==0) && (f.require_task_info.value=="true")){
        alert( "<?php echo $AppUI->_('You must select a Project.', UI_OUTPUT_JS); ?>" );
        f.task_project.focus();
    } else if ((f.task_log_task.options[f.task_log_task.selectedIndex].value==0) && (f.require_task_info.value=="true")){
        alert( "<?php echo $AppUI->_('You must select a Task.', UI_OUTPUT_JS); ?>" );
        f.task_log_task.focus();
    } else {
        f.submit();
    }
}

function delIt() {
    if (confirm( "<?php echo $AppUI->_('Are you sure that you would like to delete this task log?', UI_OUTPUT_JS).'\n'; ?>" )) {
        var form = document.AddEdit;
        form.del.value=1;
        form.submit();
    }
}
<?php 
if($tid){
?>
$(document).ready(function() {
    changeList('task_project', projects, $("select[name=project_company]").val());
    selectList('task_project', <?php echo $task['task_project']; ?>);
    changeList('task_log_task', tasks, <?php echo $task['task_project']; ?>);
    selectList('task_log_task', <?php echo $tid; ?>);
});
<?php 
}
?>

</script>

<form name="AddEdit" action="" method="post" accept-charset="utf-8">
<input type="hidden" name="m" value="timecard">
<input type="hidden" name="tab" value="0">
<input type="hidden" name="dosql" value="do_updatetasklog">
<input type="hidden" name="del" value="0">
<input type="hidden" name="require_task_info" value="<?php echo ($require_task_info ? "true" : "false"); ?>">
<input type="hidden" name="task_log_id" value="<?php echo (($tlid > 0) ? $tlid : "0"); ?>">
<input type="hidden" name="task_log_record_creator" value="<?php echo (0 == $tlid && 0 == $lid ? $AppUI->user_id : $task['task_log_record_creator']); ?>" />
<?php
    if ($tlid) {
        // maintain existing creator
        echo "<input type='hidden' name='task_log_creator' value=".$task['task_log_creator'].">";
    }
    else {
        echo "<input type='hidden' name='task_log_creator' value=".$_GET['userid'].">";
    }
?>
 
<?php 
    if(!$is_new_record){
?>
<table border="0" cellpadding="2" cellspacing="0" width="98%">
<tr>
    <td width="50%" align="right">
        <A href="javascript:delIt()"><img align="absmiddle" src="<?php echo w2PfindImage('stock_delete-16.png'); ?>" width="16" height="16" alt="Delete task log" border="0"><?php echo $AppUI->_('delete task log');?></a>
    </td>
</tr>
</table>
<?php 
    }
?>

<table cellspacing="0" cellpadding="2" border="0" width="98%" class="std">
<tr>
    <th colspan="2"><?php echo $tlid?$AppUI->_('Editing'):$AppUI->_('Creating New')." ".$AppUI->_('Task Log'); ?> </th>
</tr>

<?php 
    if(!$is_new_record){
?>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Entity');?>:</td>
    <td>
        <select id="project_company" name="project_company" size="1" class="text" style="width:250px"> 
            <option value= <?php echo '"'.$task['project_company'].'" selected>'.$companies[$task['project_company']]; ?> </option> 
        </select>
    </td>
</tr>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Project');?>:</td>
    <td>
        <select id="task_project" name="task_project" size="1" class="text" style="width:250px">
            <option value= <?php echo '"'.$task['task_project'].'" selected>'.$task['project_name']; ?> </option>
        </select>
    </td>
</tr>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Task');?>:</td>
    <td>
        <select id="task_log_task" name="task_log_task" size="1" class="text" style="width:250px"> 
            <option value= <?php echo '"'.$task['task_log_task'].'" selected>'.$task['task_name']; ?> </option> 
        </select>
    </td>
</tr>
<?php 
    } else {
?>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Entity');?>:</td>
    <td>
    <?php
        $params = 'size="1" class="text" style="width:250px" ';
        $params .= 'onchange="changeList(\'task_project\',projects, this.options[this.selectedIndex].value)"';
        echo arraySelect( $companies, 'project_company', $params, @$task['project_company'] );
    ?>
    </td>
</tr>

<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Project');?>:</td>
    <td>
        <select name="task_project" class="text" style="width:250px" onchange="changeList('task_log_task',tasks, this.options[this.selectedIndex].value)"></select>
    </td>
</tr>

<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Task');?>:</td>
    <td>
        <select name="task_log_task" class="text" style="width:250px"></select>
    </td>
</tr>
<?php 
    }
?>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Date');?>* :</td>
    <td nowrap="nowrap">
        <input type="hidden" name="task_log_date" value="<?php echo $log_date->getDate();?>" />
        <input type="text" name="log_date" id="log_date" onchange="setDate('AddEdit', 'log_date');" value="<?php echo $log_date ? $log_date->format($df) : ''; ?>" class="text" />
            <a href="javascript: void(0);" onclick="return showCalendar('log_date', '<?php echo $df ?>', 'AddEdit', null, true)">
            <img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
        </a>
    </td>
</tr>
<tr>
    <td align="right" nowrap="nowrap"><?php echo $AppUI->_('Hours');?>* :</td>
    <td>
        <input type="text" name="task_log_hours" value="<?php echo (($tlid > 0 || $tid > 0) ? $task["task_log_hours"] : "");?>" class="text" size="4" maxlength="10">
    </td>
</tr>

<tr>
    <td align="right"><?php echo $AppUI->_('Summary'); ?>* :</td>
    <td valign="middle">
        <table width="100%">
            <tr>
                <td align="left">
                    <input type="text" class="text" name="task_log_name" value="<?php echo $task["task_log_name"]; ?>" maxlength="255" size="30" />
                </td>
            </tr>
        </table>
    </td>
</tr>

<tr>
    <td align="right" valign="middle"><?php echo ($canEditTask ? $AppUI->_('Progress').' : ' : ''); ?></td>
    <td valign="middle">
        <?php echo ($canEditTask ? arraySelect($percent, 'task_percent_complete', 'size="1" class="text"', $task["task_percent_complete"]) . '%' : '<input type="hidden" name="task_percent_complete" value="0" />');?>
    </td>
</tr>
<tr>
    <td align="right" valign="middle"><?php echo $AppUI->_('Reference'); ?>:</td>
    <td valign="middle">
        <?php echo arraySelect($taskLogReference, 'task_log_reference', 'size="1" class="text"', $log->task_log_reference, true); ?>
    </td>
</tr>

<tr>
    <td align="right" valign="top" nowrap="nowrap"><?php echo $AppUI->_('Description');?>* :</td>
    <td align="left">
        <textarea name="task_log_description" cols="60" rows="6" wrap="virtual" class="textarea"><?php echo (($tlid > 0 || $tid > 0) ? $task["task_log_description"] : "");?></textarea>
    </td>
</tr>
<tr>
    <td align="right" valign="middle" nowrap="nowrap"><?php echo $AppUI->_('Cost Code');?>:</td>
    <td align="left">
        <?php
            echo arraySelect( $task_log_costcodes, 'task_log_costcodes', 'size="1" class="text" onchange="javascript:task_log_costcode.value = this.options[this.selectedIndex].value;"', '' );
        ?>
        &nbsp;->&nbsp;<input type="text" class="text" name="task_log_costcode" value="<?php echo $task["task_log_costcode"]//$log->task_log_costcode;?>" maxlength="8" size="8" />
    </td>
</tr>
<tr>
    <td align="right" valign="top" nowrap="nowrap"><?php echo $AppUI->_('Problem');?>:</td>
    <td align="left">
        <input type="checkbox" value="1" name="task_log_problem" <?php /*if($log->task_log_problem)*/if($task["task_log_problem"]){?>checked="checked"<?php }?> />
    </td>
</tr>
<tr>
    <td>
        <input class="button" type="Button" name="Cancel" value="<?php echo $AppUI->_('cancel'); ?>" onClick="javascript:if(confirm('<?php echo $AppUI->_("Are you sure you want to cancel."); ?>')){location.href = './index.php?m=timecard&tab=0';}">
    </td>
    <td align="right">
        <input class="button" type="Button" name="btnFuseAction" value="<?php echo $AppUI->_('save'); ?>" onClick="submitIt();">
    </td>
</tr>
</table>
</form>
* <?php echo $AppUI->_('indicates required field');?>
