<?php 
/**
* Adapted for Web2project by MiraKlim  on 2009/05/25
* Modified and adapted by Jonathan Dumaresq on 2005/03/08
* Modified by Matthew Arciniega on 2007/08/03
* Generates a report of the task logs for given dates for the logged user
* Adapted for Web2project 2.2 by Eureka
*/

if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

global $AppUI, $cal_sdf, $tab;

$do_report = w2PgetParam( $_POST, "do_report", 1 );
$log_pdf = w2PgetParam( $_POST, 'log_pdf', 0 );
$log_start_date = w2PgetParam( $_POST, 'log_start_date', 0 );
$log_end_date = w2PgetParam( $_POST, 'log_end_date', 0 );
$log_ignore = w2PgetParam( $_POST, 'log_ignore', 0 );
$user_id = w2PgetParam( $_GET, 'user_id', 0 );
$helpdesk_available = $this->_AppUI->isActiveModule('helpdesk'); 

$AppUI->loadCalendarJS();
$df = $AppUI->getPref('SHDATEFORMAT');
// create Date objects from the datetime fields
$start_date = intval( $log_start_date ) ? new w2p_Utilities_Date( $log_start_date ) : new w2p_Utilities_Date();
$end_date = intval( $log_end_date ) ? new w2p_Utilities_Date( $log_end_date ) : new w2p_Utilities_Date();

if ($user_id > 0) {
	$q = new w2p_Database_Query; 
	$q->addQuery('user_contact');
	$q->addTable('users');
	$q->addWhere('user_id = ' . $user_id);
	$user_contact = $q->loadresult();

	$q->clear(); 
	$q->addQuery('contact_company');
	$q->addTable('contacts');
	$q->addWhere('contact_id = ' . $user_contact);
	$company_id = $q->loadresult();

	if(!canView( "companies", $company_id )){
		$AppUI->redirect( "m=public&a=access_denied" );
	}
	$AppUI->setState( 'TimecardSelectedUser', $user_id );
}
$user_id = $AppUI->getState( 'TimecardSelectedUser' ) ? $AppUI->getState( 'TimecardSelectedUser' ) : $AppUI->user_id;
$AppUI->savePlace();

if (!$log_start_date) {
	$start_date->subtractSpan( new Date_Span( "14,0,0,0" ) );
}
$end_date->setTime( 23, 59, 59 );

?>
<script language="javascript" type="text/javascript">
function setDate( frm_name, f_date ) {
var form = document.editFrm;
	fld_date = eval( 'document.' + frm_name + '.' + f_date );
	fld_real_date = eval( 'document.' + frm_name + '.' + 'log_' + f_date );
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
</script>

<form name="datesFrm" action="index.php?m=timecard" method="post">
<table cellspacing="0" cellpadding="4" border="0" width="100%" class="std">
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('For period');?>:</td>
	<td nowrap="nowrap">
		<input type="hidden" name="log_start_date" value="<?php echo $start_date->format( FMT_TIMESTAMP_DATE );?>" />
		<input type="text" name="start_date" id="start_date" onchange="setDate('datesFrm', 'start_date');" value="<?php echo $start_date ? $start_date->format($df) : ''; ?>" class="text" />
		<a href="javascript: void(0);" onclick="return showCalendar('start_date', '<?php echo $df ?>', 'datesFrm', null, true)">
			<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
		</a>
	</td>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('to');?></td>
	<td nowrap="nowrap">
		<input type="hidden" name="log_end_date" value="<?php echo $end_date ? $end_date->format( FMT_TIMESTAMP_DATE ) : '';?>" />
		<input type="text" name="end_date" id="end_date" onchange="setDate('datesFrm', 'end_date');" value="<?php echo $end_date ? $end_date->format($df) : ''; ?>" class="text" />
		<a href="javascript: void(0);" onclick="return showCalendar('end_date', '<?php echo $df ?>', 'datesFrm', null, true)">
			<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
		</a>
	</td>
	<td nowrap="nowrap">
		<input type="checkbox" name="log_pdf" <?php if ($log_pdf) echo "checked" ?> />
		<?php echo $AppUI->_( 'Make PDF' );?>
	</td>
	<td nowrap="nowrap">
		<input type="checkbox" name="log_ignore"  <?php echo ($log_ignore) ? 'checked="checked"' : ''?>/>
		<?php echo $AppUI->_( 'Ignore 0 hours' );?>
	</td> 
	<td align="right" width="50%" nowrap="nowrap">
		<input class="button" type="submit" name="do_report" value="<?php echo $AppUI->_('submit');?>" />
	</td>
</tr>
</form>
</table>
<?php
if ($do_report) {
	$q = new w2p_Database_Query; 
	$q->addQuery('task_log.*, tasks.task_id,tasks.task_name, projects.project_name');
	if ($helpdesk_available) {$q->addQuery(' helpdesk_items.item_title'); }
	$q->addTable('task_log');
	$q->addJoin('tasks','','task_log.task_log_task = tasks.task_id');
	$q->addJoin('projects','','tasks.task_project = projects.project_id');
	if ($helpdesk_available) {
		$q->addJoin('helpdesk_items','','task_log.task_log_help_desk_id = helpdesk_items.item_id');
		$q->addWhere('(task_log_task>0 OR task_log_help_desk_id>0)');
	}  
	else {
		$q->addWhere('(task_log_task>0)');
	}
	if ($log_ignore) {  $q->addWhere('task_log.task_log_hours>0'); }
	$q->addWhere('(task_log.task_log_task = tasks.task_id OR task_log.task_log_task=0 ) ');
	$q->addWhere('task_log.task_log_creator = ' . $user_id );
	$q->addWhere('task_log.task_log_date >= \'' . $start_date->format( FMT_DATETIME_MYSQL ) . '\' AND task_log.task_log_date <= \'' . $end_date->format(FMT_DATETIME_MYSQL ) . '\'');
	$q->addOrder('task_log_date');
	$logs = $q->loadList();
	echo db_error();
?>
<table width=100% cellspacing="1" cellpadding="4" border="0" class="tbl">
	<tr>
		<th width="10%" nowrap="nowrap">
		<?php echo $AppUI->_('Date');?></th>
		<th width="7%"><?php echo $AppUI->_('Project');?></th>
		<th width="21%"><?php echo $AppUI->_('Task & Log Title');?></th>
		<th width="60%"><?php echo $AppUI->_('Description');?></th>
		<th width="2%"><?php echo $AppUI->_('Hours');?></th>
	</tr>

<?php
	$pdfdata = array();
	$pdfdata[] = array(
		$AppUI->_('Date'),
		$AppUI->_('Project'),
		$AppUI->_('Task & Log Title'),
		$AppUI->_('Description'),
		$AppUI->_('Hours')
	);
	$total_hours = 0;
	foreach ($logs as $log) {
		$date = new w2p_Utilities_Date( $log['task_log_date'] );
		$total_hours += $log["task_log_hours"];
		$project_name = $log["project_name"];
		$pdfdata[] = array(
			$date->format( $df ),
			$log['project_name'],
			$log['task_name'],
		  $log['task_log_name'],
			$log['task_log_description'],
			sprintf( "%.2f", $log['task_log_hours'] )
		);
		if ($log['task_name'] == $log['task_log_name']) {
		  $taskTitle = $log['task_name'];
		}
		elseif ($log['project_name']) {
		  $taskTitle = $log['task_name'].": ".$log['task_log_name'];
		}
		else {
			$taskTitle = $log['item_title'].": ".$log['task_log_name'];  
		}
			?>
			<tr>
				<td nowrap="nowrap" valign="top">
				<?php echo $date->format( $df );?>
				</td>
				<td nowrap="nowrap" valign="top">
				<?php echo ( ($log['project_name']) ? $log['project_name'] : $AppUI->_('Helpdesk calls')); ?>
				</td>
				<td valign="top">
				<?php echo $taskTitle;?>
				</td>
				<td valign="top">
				<?php echo $log['task_log_description'];?>
				</td>
				<td nowrap="nowrap" valign="top">
				<?php echo sprintf( "%.2f", $log['task_log_hours']);?>
				</td>
			</tr>
		<?php
		}

	$pdfdata[] = array(
		'',
		'',
		'',
		$AppUI->_('Total Hours').':',
		sprintf( "%.2f", $total_hours ),
		'',
	);
	
?>
	<tr>
		<td align="right" colspan="4"><?php echo $AppUI->_('Total Hours');?>:</td>
		<td align="right"><?php printf( "%.2f", $total_hours );?></td>
	</tr>
</table>
<?php
	if ($log_pdf) {
		// make the PDF file
		$q = new w2p_Database_Query();
		$q->addTable('users');
		$q->addQuery('user_contact');
		$q->addWhere("user_id = ".$user_id);
		$user_contact = $q->loadResult();
		$q->clear();
		$q->addTable('contacts');
		$q->addQuery('contact_first_name');
		$q->addWhere("contact_id = ".$user_contact);
		$firstName = $q->loadResult();
		$q->clear();
		$q->addTable('contacts');
		$q->addQuery('contact_last_name');
		$q->addWhere("contact_id = ".$user_contact);
		$lastName = $q->loadResult();
		$q->clear();
		$pname = $AppUI->_("For user:")." ".$firstName." ".$lastName;
		$font_dir = W2P_BASE_DIR."/lib/ezpdf/fonts";
		$temp_dir = W2P_BASE_DIR."/files/temp";
		$base_url  = w2PgetConfig('base_url');
		require( $AppUI->getLibraryClass( 'ezpdf/class.ezpdf' ) );
		$pdf =& new Cezpdf();
		$pdf->ezSetCmMargins( 1, 1.5, 1.0, 1.0 );
		$pdf->selectFont( "$font_dir/Helvetica.afm" );
		$pdf->ezText( $dPconfig['company_name'], 12 );
		$date = new w2p_Utilities_Date();
		$pdf->ezText( "\n" . $date->format( $df ) , 8 );
		$pdf->selectFont( "$font_dir/Helvetica-Bold.afm" );
		$pdf->ezText( "\n" . $AppUI->_('Task Log Report'), 12 );
		$pdf->ezText( "$pname", 15 );
		$pdf->ezText( sprintf($AppUI->_("Task log entries from %s to %s"), $start_date->format( $df ), $end_date->format( $df )), 9 );
		$pdf->ezText( "\n\n" );
		$columns = null;
		$title = null;
		$options = array(
			'showLines' => 1,
			'showHeadings' => 0,
			'fontSize' => 7,
			'rowGap' => 2,
			'colGap' => 5,
			'xPos' => 50,
			'xOrientation' => 'right',
			'width'=>'500'
		);
		$pdf->ezTable( $pdfdata, $columns, $title, $options );
		if ($fp = fopen( "$temp_dir/temp$AppUI->user_id.pdf", 'wb' )) {
			fwrite( $fp, $pdf->ezOutput() );
			fclose( $fp );
			echo "<a href=\"$base_url/files/temp/temp$AppUI->user_id.pdf\" target=\"pdf\">";
			echo $AppUI->_( "View PDF File" );
			echo "</a>";
		} else {
			echo $AppUI->_("Could not open file to save PDF.")."  ";
			if (!is_writable( $temp_dir )) {
				echo $AppUI->_("The files/temp directory is not writable.  Check your file system permissions.");
			}
		}
	}
}