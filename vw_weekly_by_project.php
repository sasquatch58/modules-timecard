<?php
/**
* Adapted for Web2project by MiraKlim
* Adapted for Web2project 2.1 by Eureka and colleagues from his company (DILA)
* by kirkawolff : Fix vw_weekly_by_project date/calendar handling, did not allow entry for <Report on Date Range> submission.
**/
	if (!defined('W2P_BASE_DIR')) {
		die('You should not access this file directly.');
	}

	global $tab, $AppUI, $cal_sdf;

	$show_possible_hours_worked = $TIMECARD_CONFIG['show_possible_hours_worked'];
	$helpdesk_available = $AppUI->isActiveModule('helpdesk');

	$AppUI->loadCalendarJS();
	// get date format
	$df = $AppUI->getPref('SHDATEFORMAT');
	//grab hours per day from config
	$min_hours_day = w2PgetConfig("daily_working_hours");
	//compute hours/week from config
	$min_hours_week =count(explode(",",w2PgetConfig("cal_working_days"))) * $min_hours_day;
	//How many weeks are we going to show?
	$week_count = 4;
	$report_department_types = array(
		'project' => $AppUI->_('Project Department'),
		'user' => $AppUI->_('User Department')
		);
	if (isset( $_GET['raw_start_date'] )) {
		$AppUI->setState( 'TimecardWeeklyReportStartDate', $_GET['raw_start_date'] );
	}
	else if (isset( $_GET['start_date'] )) {
		$AppUI->setState( 'TimecardWeeklyReportStartDate', $_GET['start_date'] );
	}
	$start_day = new w2p_Utilities_Date( $AppUI->getState( 'TimecardWeeklyReportStartDate' ) ? $AppUI->getState( 'TimecardWeeklyReportStartDate' ) : NULL);
	if (isset( $_GET['raw_end_date'] )) {
		$AppUI->setState( 'TimecardWeeklyReportEndDate', $_GET['raw_end_date'] );
	}
	else if (isset( $_GET['end_date'] )) {
		$AppUI->setState( 'TimecardWeeklyReportEndDate', $_GET['end_date'] );
	}
	$end_day = new w2p_Utilities_Date( $AppUI->getState( 'TimecardWeeklyReportEndDate' ) ? $AppUI->getState( 'TimecardWeeklyReportEndDate' ) : NULL);
	if (isset( $_GET['company_id'] )) {
		$AppUI->setState( 'TimecardWeeklyReportCompanyId', $_GET['company_id'] );
	}
	$company_id = $AppUI->getState( 'TimecardWeeklyReportCompanyId' ) ? $AppUI->getState( 'TimecardWeeklyReportCompanyId' ) : 0;
	if (isset( $_GET['user_id'] )) {
		$AppUI->setState( 'TimecardWeeklyReportPeopleId', $_GET['user_id'] );
	}
	$user_id = $AppUI->getState( 'TimecardWeeklyReportPeopleId' ) ? $AppUI->getState( 'TimecardWeeklyReportPeopleId' ) : 0;
	if (isset( $_GET['browse'] )) {
		$AppUI->setState( 'TimecardWeeklyReportBrowse', $_GET['browse'] );
	}
	$browse = $AppUI->getState( 'TimecardWeeklyReportBrowse')=='0'?false:true;
	if (isset( $_GET['report_department_type'] )) {
		$AppUI->setState( 'TimecardWeeklyReportDepartmentType', $_GET['report_department_type'] );
	}
	$report_department_type = $AppUI->getState( 'TimecardWeeklyReportDepartmentType')!=NULL?$AppUI->getState( 'TimecardWeeklyReportDepartmentType'):key($report_department_types);
	//set that to just midnight so as to grab the whole day
	$date = $start_day->format("%Y-%m-%d")." 00:00:00";
	$start_day -> setDate($date, DATE_FORMAT_ISO);
	if($browse){
		$today_weekday = $start_day -> getDayOfWeek();
		//roll back to the first day of that week, regardless of what day was specified
		$rollover_day = '0';
		$new_start_offset = $rollover_day - $today_weekday;
		$start_day -> addDays($new_start_offset);
		//last day of that week, add 6 days
		$end_day = new w2p_Utilities_Date ();
		$end_day -> copy($start_day);
		$end_day -> addDays(6);
	} else {
		$week_count = 1;
	}
	//set that to just before midnight so as to grab the whole day
	$date = $end_day->format("%Y-%m-%d")." 23:59:59";
	$end_day -> setDate($date, DATE_FORMAT_ISO);
	//Get hash of users
	$q = new w2p_Database_Query; 
	$q->addQuery('user_id,concat(contact_first_name,\' \',contact_last_name) as name');
	$q->addTable('users');
	$q->addJoin('contacts','','users.user_contact=contacts.contact_id');
	$q->addOrder('contact_first_name, contact_last_name');
	$result = $q->loadList();
	$people = array();
	foreach($result as $row){
		$people[$row['user_id']] = $row;
		$users[$row['user_id']] = $row['name'];
	}
	unset($result);
	$users = arrayMerge( array( 0 => $AppUI->_('All Users') ), $users );
	//Get hash of departments
	$q->addQuery('dept_id, dept_name ');
	$q->addTable('departments');
	$q->addOrder('dept_name');
	$result = $q->loadList();
	$departments = array();
	foreach($result as $row){
		$departments[$row['dept_id']] = $row['dept_name'];
	}
	unset($result);
	$q->addQuery('project_id,project_name,company_name');
	$q->addTable('projects');
	$q->addJoin('companies','','projects.project_company=companies.company_id');

	if($company_id>0){
		$where = 'projects.project_company = ' . $company_id;
	}
	$q->addWhere($where);
	$q->addOrder('project_name');
	$result = $q->loadList();
	for($i=0;$i<count($result);$i++){
		$project_id = $result[$i]['project_id'];
		$ids[] = $project_id;
		unset($project_id);
	}
	unset($result);
	if(isset($ids))
	for($i=0;$i<$week_count;$i++){
		//set that to just midnight so as to grab the whole day
		$date = $start_day->format("%Y-%m-%d")." 00:00:00";
		$start_day -> setDate($date, DATE_FORMAT_ISO);
		if($browse){
			$today_weekday = $start_day -> getDayOfWeek();
			//roll back to the first day of that week, regardless of what day was specified
			$rollover_day = '0';
			$new_start_offset = $rollover_day - $today_weekday;
			$start_day -> addDays($new_start_offset);
			//last day of that week, add 6 days
			$end_day = new w2p_Utilities_Date ();
			$end_day -> copy($start_day);
			$end_day -> addDays(6);
		}
		//set that to just midnight so as to grab the whole day
		$date = $start_day->format("%Y-%m-%d")." 00:00:00";
		$start_day -> setDate($date, DATE_FORMAT_ISO);
		//set that to just before midnight so as to grab the whole day
		$date = $end_day->format("%Y-%m-%d")." 23:59:59";
		$end_day -> setDate($date, DATE_FORMAT_ISO);
		$start_month = $start_day->format("%b");
		$end_month = $end_day->format("%b");
		$start_date = $start_day->format("%e");
		$end_date = $end_day->format("%e");
		$start_data_pretty[$i] =	"$start_month $start_date-".($start_month==$end_month?$end_date:"$end_month $end_date");
		$start_data_linkable[$i] =	urlencode($start_day->getDate()) ;
		// 8:42 PM 2007-08-05 MatthewA : corrected query string to grab departments correctly
		$q = new w2p_Database_Query; 
		$q->addQuery('task_log_creator,contact_department,projects.project_id,project_name,department_id,project_company,company_name,sum(task_log_hours) as hours');
		$q->addTable('task_log');
		$q->addJoin('tasks','','task_log.task_log_task = tasks.task_id');
		$q->addJoin('projects','','tasks.task_project = projects.project_id');
		$q->addJoin('users','','task_log.task_log_creator = users.user_id');
		$q->addJoin('companies','','projects.project_company = companies.company_id');
		$q->addJoin('project_departments','','projects.project_id = project_departments.project_id');
		$q->addJoin('contacts','','users.user_contact = contacts.contact_id');
		$q->addWhere('( projects.project_id in (' . implode(', ',$ids) . ') OR task_log_task=0 ) ');
		$q->addWhere(' task_log_date >= \'' . $start_day->format(FMT_DATETIME_MYSQL) . '\' AND task_log_date <= \'' .$end_day->format(FMT_DATETIME_MYSQL) . '\'' );
		if ($user_id>0) {$q->addWhere(' task_log_creator = ' . $user_id ); }
		$q->addGroup('project_company,projects.project_id,task_log.task_log_creator');
		$result = $q->loadList();
		// 8:42 PM 2007-08-05 MatthewA : corrected field names
		$department_field = $report_department_type=='project'?'department_id':'contact_department';
		foreach($result as $row){
			//pull the department numbers apart, and populate them with their names.
			if($row[$department_field]!=null && $row[$department_field]!=0 && strlen($row[$department_field])>0){
				$department_list = explode(',',$row[$department_field]);
				for($c=0;$c<count($department_list);$c++){
					if(isset($departments[$department_list[$c]])){
						$department_list[$c] = $departments[$department_list[$c]];
					}
				}
			} else {
				$department_list = array($AppUI->_('Other/No Department'));
			}
			foreach($department_list as $department){
				if(!isset($projects[$row['company_name']]['departments'][$department]['projects'][$row['project_id']]['project_name'])){
					$projects[$row['company_name']]['departments'][$department]['projects'][$row['project_id']] = array(
						'project_name' => $row['project_name']
					);
				}
				$projects[$row['company_name']]['departments'][$department]['projects'][$row['project_id']]['users'][$row['task_log_creator']][$i] = $row['hours']/count($department_list);
				
				@$projects[$row['company_name']]['departments'][$department]['projects'][$row['project_id']]['totals'][$i] += $row['hours']/count($department_list);
				@$projects[$row['company_name']]['departments'][$department]['totals'][$i] += $row['hours']/count($department_list);
				@$projects[$row['company_name']]['totals'][$i] += $row['hours']/count($department_list);
			}
		}
		unset($result);
		if($browse)
			$start_day -> addDays(-7);
	}
	$q->addQuery('company_id, company_name');
	$q->addTable('companies');
	$q->addOrder('company_name');
	$companies = arrayMerge( array( 0 => $AppUI->_('All Entities') ), $q->loadHashList() );
	//last day of that week, add 6 days
	$next_day = new w2p_Utilities_Date ();
	$next_day -> copy($start_day);
	$next_day -> addDays($week_count*7*2);
?>
<script language="javascript" type="text/javascript">
function setDate( frm_name, f_date ) {
	var form = document.editFrm;
	fld_date = eval( 'document.' + frm_name + '.' + f_date );
	fld_real_date = eval( 'document.' + frm_name + '.' + 'raw_' + f_date );
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
<form name="frmSelect" action="" method="get">
	<input type="hidden" name="m" value="timecard">
	<input type="hidden" name="report_type" value="weekly_by_project">
	<input type="hidden" name="tab" value="<?php echo $tab; ?>">
	<table cellspacing="1" cellpadding="2" border="0" width="100%">
	<tr>
		<td width="1%" valign="top" nowrap="nowrap"><?php echo arraySelect( $companies, 'company_id', 'size="1" class="text" id="medium" onchange="document.frmSelect.submit()"',
													$company_id )?><?php echo arraySelect( $users, 'user_id', 'size="1" class="text" id="medium" onchange="document.frmSelect.submit()"',
													$user_id )?></td>
		<td width="98%" align="right" valign="top">
			<table cellpadding="0" cellspacing="0" width="1%">
				<tr>
					<?php
						$prev_url = "?m=timecard&tab=$tab&report_type=weekly_by_project&start_date=".urlencode($start_day->getDate())."&browse=1";
						$next_url = "?m=timecard&tab=$tab&report_type=weekly_by_project&start_date=".urlencode($next_day->getDate())."&browse=1";
					?>
					<td width="95%">&nbsp;</td>
					<td width="1%"><a href="<?php echo $prev_url?>"><img src="<?php echo w2PfindImage('next.gif'); ?>" width="16" height="16" alt="<?php echo $AppUI->_( 'previous' );?>" border="0"></a></td>
					<td width="1%" nowrap="nowrap" style="padding-left:5px"><a href="<?php echo $prev_url?>"><?php echo $AppUI->_('previous')?> <?php echo	$week_count?> <?php echo $AppUI->_('weeks')?></a></td>
					<td width="1%">&nbsp;|&nbsp;</td>
					<td width="1%" nowrap="nowrap" style="padding-right:5px"><a href="<?php echo $next_url?>"><?php echo $AppUI->_('next')?> <?php echo	$week_count?> <?php echo $AppUI->_('weeks')?></a></td>
					<td width="1%"><a href="<?php echo $next_url?>">
					<img src="<?php echo w2PfindImage('next.gif'); ?>" width="16" height="16" alt="<?php echo $AppUI->_( 'next' );?>" border="0"></a></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td width="98%" valign="top" colspan="2">
			<table cellpadding="0" cellspacing="0" width="100%">
				<tr>
					<td width="97%"><?php echo arraySelect($report_department_types, 'report_department_type',	'size="1" class="text" id="medium" onchange="document.frmSelect.submit()"', $report_department_type)?></td>
					<td nowrap="nowrap" width="1%">
						<input type="hidden" name="browse" value="0" />
						<input type="hidden" name="raw_start_date" value="<?php echo $start_day->format( FMT_TIMESTAMP_DATE );?>" />
						<input type="text" name="start_date" id="start_date" onchange="setDate('frmSelect', 'start_date');" value="<?php echo $start_day ? $start_day->format($df) : ''; ?>" class="text" />
						<a href="javascript: void(0);" onclick="return showCalendar('start_date', '<?php echo $df ?>', 'frmSelect', null, true)">
							<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
						</a>
					</td>
					<td nowrap="nowrap" width="1%">
						<input type="hidden" name="raw_end_date" value="<?php echo $end_day ? $end_day->format( FMT_TIMESTAMP_DATE ) : '';?>" />
						<input type="text" name="end_date" id="end_date" onchange="setDate('frmSelect', 'end_date');" value="<?php echo $end_day ? $end_day->format( $df ) : '';?>" class="text" />
						<a href="javascript: void(0);" onclick="return showCalendar('end_date', '<?php echo $df ?>', 'frmSelect', null, true)">
						<img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" /> 
						</a>
					</td>
					<td width="1%">
						<input type="submit" value="<?php echo $AppUI->_('Report on Date Range')?>">
					</td>
				</tr>
			</table>
		</td>
	</tr>
	</table>
</form>
<table cellspacing="1" cellpadding="2" border="0" class="std" width="100%">
<tr>
	<th><?php echo $AppUI->_('Project/UserName')?></th>
<?php
	if(isset($start_data_pretty))
	for($i=$week_count-1;$i>=0;$i--){
?>
	<th width="120px"><?php echo $start_data_pretty[$i]?></th>
<?php
	}
?>
</tr>
<?php
	if(!isset($projects)){
?>
	<tr><td align="center" colspan="<?php echo ($week_count+1)?>"><?php echo $AppUI->_('No Data Available')?></td></tr>
<?php
	} else {
		$image_straight = '<img src="' . w2PfindImage('verticle-dots.png','timecard') . '" width="16" height="12" border="0">';
		$image_elbow= '<img src="' . w2PfindImage('corner-dots.gif') . '" width="16" height="12" border="0">';
		$image_shim= '<img src="' . w2PfindImage('shim.gif') . '" width="16" height="12" border="0">';
		if(isset($projects))
		foreach($projects as $id => $company){
			if(!next($projects)){
				$last_company=true;
			} else {
				$last_company=false;
			}
?>
<tr>
	<td style="background:#8AC6FF;"><?php echo $id?></td>
<?php
	for($i=$week_count-1;$i>=0;$i--){
?>
	<td style="background:#8AC6FF;" align="right"><?php echo isset($company['totals'][$i])?number_format(round($company['totals'][$i],2),2):"0.00"?></td>
<?php
	}
?>
</tr>
<?php
	foreach($company['departments'] as $id => $department){
		if(!next($company['departments'])){
			$last_department=true;
		} else {
			$last_department=false;
		}
?>
<tr>
	<td style="background:#A7D4FF;"><?php echo $image_elbow?><?php echo $id?></td>
<?php
	for($i=$week_count-1;$i>=0;$i--){
?>
	<td style="background:#A7D4FF;" align="right"><?php echo isset($department['totals'][$i])?number_format(round($department['totals'][$i],2),2):"0.00"?></td>
<?php
	}
?>
</tr>
<?php
	foreach($department['projects'] as $id => $project){
		if(!next($department['projects'])){
			$last_project=true;
		} else {
			$last_project=false;
		}
	//only display projects with time assigned
	if(isset($project['totals'])){
?>
<tr>
	<td nowrap="nowrap" style="background:#C0E0FF;">
	<?php echo !$last_department?$image_straight:$image_shim?>
	<?php echo $image_elbow?>
	<a href="?m=projects&a=view&project_id=<?php echo $id?>">
	<?php echo ( ($project['project_name']) ? $project['project_name'] : $AppUI->_('Helpdesk calls')); ?></a></td>
<?php
	for($i=$week_count-1;$i>=0;$i--){
?>
	<td align="right" style="background:#C0E0FF;"><?php echo isset($project['totals'][$i])?number_format(round($project['totals'][$i],2),2):"0.00"?></td>
<?php
	}
?>
</tr>
<?php
		if(isset($project['users']))
		foreach($project['users'] as $id => $person){
?>
<tr>
	<td><?php echo !$last_department?$image_straight:$image_shim?><?php echo !$last_project?$image_straight:$image_shim?><?php echo $image_elbow?><?php echo isset($people[$id]['name'])?$people[$id]['name']:$id?></td>
<?php
			for($i=$week_count-1;$i>=0;$i--){
				 $hours = isset($person[$i])?$person[$i]:0;
				 $hours = round($hours,2);
?>
	<td align="right"><a href="?m=timecard&user_id=<?php echo $id?>&tab=0&start_date=<?php echo $start_data_linkable[$i]?>"><?php echo number_format($hours,2)?></a></td>
<?php
			}
?>
</tr>
<?php
		}
?>
</tr>
<?php
	}
	}
	}
	}
}
?>
</table>