<?php
/**
* Adapted for Web2project by MiraKlim
**/
//Based largely on the page with the same funtion in the existing TimeTrack module by ajdonnison.  

// check permissions

global $AppUI, $cal_sdf;
$AppUI->loadCalendarJS();

$m = $AppUI->checkFileName(w2PgetParam( $_GET, 'm', getReadableModule() ));
$canEdit = canEdit( $m );
if (!$canEdit) {
	$AppUI->redirect(ACCESS_DENIED);
}

$df = $AppUI->getPref('SHDATEFORMAT');

$tlid = (int)w2PgetParam( $_GET, 'tlid', 0 );

$q = new w2p_Database_Query; 
$q->addQuery('task_log.*, item_id, item_project_id, item_title, item_company_id, project_name');
$q->addTable('task_log');
$q->addJoin('helpdesk_items','','task_log_help_desk_id = item_id');
$q->addJoin('projects','','item_project_id = project_id');
$q->addWhere('task_log_id = '. $tlid . ' AND ' .getItemPerms());
$helpdeskItemTask = $q->loadHash();

$is_new_record = !$tlid;
$helpdeskItemTask_found = $helpdeskItemTask['item_id']!=FALSE;
$require_task_info = $is_new_record || $helpdeskItemTask_found;

Global $TIMECARD_CONFIG;
//Prevent users from editing other ppls timecards.
$can_edit_other_timesheets = $TIMECARD_CONFIG['minimum_edit_level']>=$AppUI->user_type;
if (!$can_edit_other_timesheets){
	if(isset($_GET['tlid']) && ((isset($helpdeskItemTask['task_log_creator']) && $helpdeskItemTask['task_log_creator'] != $AppUI->user_id)) ){
		$AppUI->redirect(ACCESS_DENIED);
	}
}

if (isset( $helpdeskItemTask['task_log_date'] )) {
	$log_date = new w2p_Utilities_Date( $helpdeskItemTask['task_log_date'] ); 
} else if (isset( $_GET['date'] )) {
	$log_date = new w2p_Utilities_Date($_GET['date']); 
} else {
	$log_date = new w2p_Utilities_Date();
}

$helpdeskItemTasks = array();
$project = array();
$companies = array( '0'=>'' );

// get user -> tasks
$q = new w2p_Database_Query; 
$q->addQuery('h.*, p.project_name, c.company_name, c.company_id');
$q->addTable('helpdesk_items','h');
$q->addJoin('projects','p','h.item_project_id = p.project_id');
$q->addJoin('companies','c','h.item_company_id = c.company_id');
$q->addWhere(getItemPerms());
$q->addOrder('p.project_name, h.item_title');
$res = $q->exec();

while ($row = db_fetch_assoc( $res )) {
// collect help desk items in js format
	$helpdeskItemTasks[$row['item_id']] = "[{$row['item_project_id']},{$row['item_id']},'".addslashes($row['item_title'])."',{$row['item_company_id']}]";
// collect projects in js format
	$projects[$row['item_project_id']] = "[{$row['company_id']},{$row['item_project_id']},'".addslashes($row['project_name'])."']";
// collect companies in normal format
	$companies[$row['item_company_id']] = $row['company_name'];
};

if ($helpdeskItemTask_found)
{
	// need to add the entry for the helpdesk itself as that was not found
	$helpdeskItemTasks[$helpdeskItemTask['item_id']] = "[{$helpdeskItemTask['item_project_id']}, {$helpdeskItemTask['item_id']}, '{$helpdeskItemTask['item_title']}', {$helpdeskItemTask['item_company_id']}]";
	// get the project name
  $q = new w2p_Database_Query; 
  $q->addQuery('project_name');
  $q->addTable('projects');
  $q->addWhere('project_id = '. $helpdeskItemTask['item_project_id']);
  $itemCompanyName = $q->LoadResult();
	// collect projects in js format
	$projects[$helpdeskItemTask['item_project_id']] = "[{$helpdeskItemTask['item_company_id']},{$helpdeskItemTask['item_project_id']}, '{$itemCompanyName}']";
	// get the company name
  $q = new w2p_Database_Query; 
  $q->addQuery('company_name');
  $q->addTable('companies');
  $q->addWhere('company_id =' . $helpdeskItemTask['item_company_id']);
  $companies[$helpdeskItemTask['item_company_id']] = $q->LoadResult();
}

##
## Set up JavaScript arrays
##
$ua = $_SERVER['HTTP_USER_AGENT'];
$isMoz = strpos( $ua, 'Gecko' ) !== false;

if(isset($projects)){
	$projects = array_unique( $projects );
	reset( $projects );
} else {
	$projects = array();
}

$s = "\nvar helpDeskItems = new Array(".implode( ",\n", $helpdeskItemTasks ).")";
$s .= "\nvar projects = new Array(".implode( ",\n", $projects ).")";

echo "<script language=\"javascript\">$s</script>";
?>

<script language="javascript">

function setDate( frm_name, f_date ) {
	fld_date = eval( 'document.' + frm_name + '.' + f_date );
	fld_real_date = eval( 'document.' + frm_name + '.' + 'task_' + f_date );
	if (fld_date.value.length>0) {
      if ((parseDate(fld_date.value))==null) {
						alert("<?php echo $AppUI->_('The Date/Time you typed does not match your prefered format, please retype.'); ?>");
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
function emptyList( list ) {
<?php if ($isMoz) { ?>
	list.options.length = 0;
<?php } else { ?>
	while( list.options.length > 0 )
		list.options.remove(0);
<?php } ?>
}

function addToList( list, text, value ) {
<?php if ($isMoz) { ?>
	list.options[list.options.length] = new Option(text, value);
<?php } else { ?>
	var newOption = document.createElement("OPTION");
	newOption.text = text;
	newOption.value = value;
 	list.add( newOption, 0 );
<?php } ?>
}

function changeList( listName, source, target ) {
	var f = document.AddEdit;
	var list = eval( 'f.'+listName );
	
// clear the options
	emptyList( list );
// refill the list based on the target
	for (var i=0, n = source.length; i < n; i++) {
		if( source[i][0] == target ) {
			addToList( list, source[i][2], source[i][1] );
		}
	}
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
	if(f.task_log_help_desk_id && f.task_log_help_desk_id.options.length > 0){
		f.task_log_name.value = f.task_log_help_desk_id.options[f.task_log_help_desk_id.selectedIndex].text;
	}
	if (f.task_log_hours.value.length < 1) {
		alert( "<?php echo $AppUI->_('Please enter hours worked'); ?>" );
		f.task_log_hours.focus();
	} else if (chours > 24) {
		alert( "<?php echo $AppUI->_('Hours cannot exceed 24'); ?>" );
		f.task_log_hours.focus();
	} else if (f.task_log_description.value.length<1) {
		alert( "<?php echo $AppUI->_('Please enter a worthwhile comment.'); ?>" );
		f.task_log_description.focus();
	} else if ((f.task_log_help_desk_id.options[f.task_log_help_desk_id.selectedIndex].value==0) && (f.require_task_info.value=="true")){
		alert( "<?php echo $AppUI->_('You must select a Help Desk Item.'); ?>" );
		f.task_log_help_desk_id.focus();
	} else {
		f.submit();
	}
}

function delIt() {
	if (confirm( "<?php echo $AppUI->_('Are you sure that you would like to delete this task log?').'\n'; ?>" )) {
		var form = document.AddEdit;
		form.del.value=1;
		form.submit();
	}
}
</script>

<form name="AddEdit" action="" method="post">
<input type="hidden" name="m" value="timecard">
<input type="hidden" name="tab" value="0">
<input type="hidden" name="dosql" value="do_updatetasklog">
<input type="hidden" name="del" value="0">
<input type="hidden" name="require_task_info" value="<?php echo ($require_task_info ? "true" : "false"); ?>">
<input type="hidden" name="task_log_id" value="<?php echo (($tlid > 0) ? $tlid : "0"); ?>">
<input type="hidden" name="task_log_record_creator" value="<?php echo (0 == $tlid ? $AppUI->user_id : $helpdeskItemTask['task_log_record_creator']); ?>" />
<?php
	if ($tlid)
	{
		// maintain existing creator
		echo "<input type='hidden' name='task_log_creator' value=".$helpdeskItemTask['task_log_creator'].">";
	}
	else
	{
		echo "<input type='hidden' name='task_log_creator' value=".$AppUI->user_id.">";
	}
?>
 
<?php 
	if(!$is_new_record && $helpdeskItemTask_found){
?>
<table border="0" cellpadding="4" cellspacing="0" width="98%">
<tr>
	<td width="50%" align="right">
		<A href="javascript:delIt()"><img align="absmiddle" src="<?php echo w2PfindImage('stock_delete-16.png'); ?>" width="16" height="16" alt="Delete this project" border="0"><?php echo $AppUI->_('delete helpdesk log');?></a>
	</td>
</tr>
</table>
<?php 
	}
?>

<table cellspacing="0" cellpadding="4" border="0" width="98%" class="std">
<tr>
	<th colspan="2"><?php echo $tlid?$AppUI->_('Editing'):$AppUI->_('Creating New')." ".$AppUI->_('Help Desk Log'); ?> </th>
</tr>
<?php 
	if(!$is_new_record && $helpdeskItemTask_found){
?>
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Entity');?>:</td>
	<td>
	  <select id="project_company" name="project_company" size="1" class="text" style="width:250px"> 
      <option value= <?php echo '"'.$helpdeskItemTask['item_company_id'].'" selected>'.$companies[$helpdeskItemTask['item_company_id']]; ?> </option> 
    </select>
	</td>
</tr>
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Project');?>:</td>
	<td>
		<select id="task_project" name="task_project" size="1" class="text" style="width:250px">
  		<option value= <?php echo '"'.$helpdeskItemTask['item_project_id'].'" selected>'.$helpdeskItemTask['project_name']; ?> </option>
    </select>
	</td>
</tr>
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Help Desk Item');?>:</td>
	<td>
	  <select id="task_log_help_desk_id" name="task_log_help_desk_id" size="1" class="text" style="width:250px"> 
      <option value= <?php echo '"'.$helpdeskItemTask['task_log_help_desk_id'].'" selected>'.$helpdeskItemTask['item_title']; ?> </option> 
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
		$params .= 'onchange="changeList(\'item_project_id\',projects, this.options[this.selectedIndex].value,0);changeList(\'task_log_help_desk_id\',helpDeskItems, 0,3)"';
		echo arraySelect( $companies, 'item_company_id', $params, @$helpdeskItemTask['item_company_id'] );
	?>
	</td>
</tr>

<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Project');?>:</td>
	<td>
		<select name="item_project_id" class="text" style="width:250px" onchange="changeList('task_log_help_desk_id',helpDeskItems, this.options[this.selectedIndex].value,0)"></select>
	</td>
</tr>
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Help Desk Item');?>* :</td>
	<td>
		<select name="task_log_help_desk_id" class="text" style="width:250px"></select>
		<input type="hidden" name="task_log_name" value="" 
<?php
	if(!$is_new_record && $helpdeskItemTask_found){
    echo ('disabled="disabled">');
  } else { 
	  echo '>';
  }
?>
	</td>
</tr>
<?php 
	}
?>

<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Date');?>* :</td>
	<td>
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
		<input type="text" name="task_log_hours" value="<?php echo (($tlid > 0) ? $helpdeskItemTask["task_log_hours"] : "");?>" class="text" size="4" maxlength="10">
	</td>
</tr>

<tr>
	<td align="right"><?php echo $AppUI->_('Summary'); ?>* :</td>
    <td valign="middle">
        <table width="100%">
            <tr>
                <td align="left">
                    <input type="text" class="text" name="task_log_name" value="<?php echo $helpdeskItemTask["task_log_name"]; ?>" maxlength="255" size="30" />
                </td>
            </tr>
        </table>
	</td>
</tr>

<tr>
	<td align="right" valign="top" nowrap="nowrap"><?php echo $AppUI->_('Description');?>* :</td>
	<td align="left">
		<textarea name="task_log_description" cols="60" rows="6" wrap="virtual" class="textarea"><?php echo (($tlid > 0) ? $helpdeskItemTask["task_log_description"] : "");?></textarea>
	</td>
</tr>

<tr>
	<td>
		<input class="button" type="Button" name="Cancel" value="<?php echo $AppUI->_('cancel');?>" onClick="javascript:if(confirm('<?php echo $AppUI->_("Are you sure you want to cancel."); ?>')){location.href = './index.php?m=timecard&tab=0';}">
	</td>
	<td align="right">
		<input class="button" type="Button" name="btnFuseAction" value="<?php echo $AppUI->_('save');?>" onClick="submitIt();">
	</td>
</tr>

</table>
</form>
* <?php echo $AppUI->_('indicates required field');?>