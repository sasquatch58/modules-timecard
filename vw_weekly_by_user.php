<?php
/**
* Adapted for Web2project by MiraKlim
* Adapted for Web2project 2.1 by Eureka and colleagues from his company (DILA)
**/

global $tab,$TIMECARD_CONFIG;
$show_possible_hours_worked = $TIMECARD_CONFIG['show_possible_hours_worked'];
//grab hours per day from config
$min_hours_day = $w2Pconfig['daily_working_hours'];
//compute hours/week from config
$min_hours_week =count(explode(",",$w2Pconfig["cal_working_days"])) * $min_hours_day;
// get date format
$df = $this->_AppUI->getPref('SHDATEFORMAT');

//How many weeks are we going to show?
$week_count = 4;

if (isset( $_GET['start_date'] )) {
	$this->_AppUI->setState( 'TimecardWeeklyReportStartDate', $_GET['start_date'] );
}
$start_day = new w2p_Utilities_Date( $this->_AppUI->getState( 'TimecardWeeklyReportStartDate' ) ? $this->_AppUI->getState( 'TimecardWeeklyReportStartDate' ) : NULL);

if (isset( $_GET['company_id'] )) {
	$this->_AppUI->setState( 'TimecardWeeklyReportCompanyId', $_GET['company_id'] );
}
$company_id = $this->_AppUI->getState( 'TimecardWeeklyReportCompanyId' ) ? $this->_AppUI->getState( 'TimecardWeeklyReportCompanyId' ) : 0;
//set that to just midnight so as to grab the whole day
$date = $start_day->format("%Y-%m-%d")." 00:00:00";
$start_day -> setDate($date, DATE_FORMAT_ISO);

$today_weekday = $start_day -> getDayOfWeek();

//roll back to the first day of that week, regardless of what day was specified
$rollover_day = '0';
$new_start_offset = $rollover_day - $today_weekday;
$start_day -> addDays($new_start_offset);

//last day of that week, add 6 days
$end_day = new w2p_Utilities_Date ();
$end_day -> copy($start_day);
$end_day -> addDays(6);

//set that to just before midnight so as to grab the whole day
$date = $end_day->format("%Y-%m-%d")." 23:59:59";
$end_day -> setDate($date, DATE_FORMAT_ISO);

$selects = array();
$join = array();

$q = new w2p_Database_Query; 
$q->addQuery('user_id,concat(contact_first_name,\' \',contact_last_name) as name,company_name');
$q->addTable('users', 'u');
$q->addJoin('contacts','ct','u.user_contact=ct.contact_id');
$q->addJoin('companies','co','co.company_id=ct.contact_company');
$q->addQuery('contact_email');

if($company_id>0){
	$where .= 'ct.contact_company = ' . $company_id;
}
$q->addWhere($where);
$q->addOrder('contact_first_name, contact_last_name');
$result = $q->loadList();
for($i=0;$i<count($result);$i++){
	$people[$result[$i]['user_id']] = $result[$i];
	$ids[] = $result[$i]['user_id'];
}
unset($result);

if(isset($ids)) {
	for($i=0;$i<$week_count;$i++){
		//set that to just midnight so as to grab the whole day
		$date = $start_day->format("%Y-%m-%d")." 00:00:00";
		$start_day -> setDate($date, DATE_FORMAT_ISO);
		$today_weekday = $start_day -> getDayOfWeek();
		//roll back to the first day of that week, regardless of what day was specified
		$rollover_day = '0';
		$new_start_offset = $rollover_day - $today_weekday;
		$start_day -> addDays($new_start_offset);

		//last day of that week, add 6 days
		$end_day = new w2p_Utilities_Date ();
		$end_day -> copy($start_day);
		$end_day -> addDays(6);

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
	
		$start_data_pretty[$i] =  "$start_month $start_date-".($start_month==$end_month?$end_date:"$end_month $end_date");
		$start_data_linkable[$i] =  urlencode($start_day->getDate()) ;
		$q = new w2p_Database_Query; 
		$q->addQuery('task_log_creator,sum(task_log_hours) as hours');
		$q->addTable('task_log');
		$q->addWhere('task_log_date >= \'' . $start_day->format( FMT_DATETIME_MYSQL )  
								.'\' AND task_log_date <= \'' . $end_day->format( FMT_DATETIME_MYSQL )
								.'\' AND task_log_creator in (' . implode(', ',$ids) . ')');
		$q->addGroup('task_log_creator');
		$result = $q->loadList();

		foreach($result as $row){
			$people[$row['task_log_creator']][$i] = $row['hours'];
		}

		$date = $start_day->format("%Y-%m-%d")." 12:00:00";
		$start_day -> setDate($date, DATE_FORMAT_ISO);
		$start_day -> addDays(-7);
	}
}
$q = new w2p_Database_Query;
$q->addQuery('company_id, company_name');
$q->addTable('companies');
$q->addOrder('company_name');
$companies = arrayMerge( array( 0 => $this->_AppUI->_('All Entities') ), $q->loadHashList() );

$next_day = new w2p_Utilities_Date ();
$next_day -> copy($start_day);
$next_day -> addDays($week_count*7*2);
?>
<form name="frmCompanySelect" action="" method="get">
	<input type="hidden" name="m" value="timecard">
	<input type="hidden" name="report_type" value="weekly_by_user">
	<input type="hidden" name="tab" value="<?php echo $tab?>">
	<table cellspacing="1" cellpadding="2" border="0" width="100%">
	<tr>
		<td width="95%"><?php echo arraySelect( $companies, 'company_id', 'size="1" class="text" id="medium" onchange="document.frmCompanySelect.submit()"', $company_id )?></td>
		<?php
		$prev_url = "?m=timecard&tab=$tab&report_type=weekly_by_user&start_date=".urlencode($start_day->getDate());
		$next_url = "?m=timecard&tab=$tab&report_type=weekly_by_user&start_date=".urlencode($next_day->getDate());
		?>
		<td width="1%" nowrap="nowrap"><a href="<?php echo $prev_url?>"<?php echo w2PfindImage('prev.gif'); ?>" width="16" height="16" alt="<?php echo $this->_AppUI->_( 'previous' );?>" border="0"></a></td>
		<td width="1%" nowrap="nowrap"><a href="<?php echo $prev_url?>"><?php echo $this->_AppUI->_('previous')?> <?php echo  $week_count?> <?php echo $this->_AppUI->_('weeks')?></a></td>
		<td width="1%" nowrap="nowrap">&nbsp;|&nbsp;</td>
		<td width="1%" nowrap="nowrap"><a href="<?php echo $next_url?>"><?php echo $this->_AppUI->_('next')?> <?php echo  $week_count?> <?php echo $this->_AppUI->_('weeks')?></a></td>
		<td width="1%" nowrap="nowrap"><a href="<?php echo $next_url?>"><img src="<?php echo w2PfindImage('next.gif'); ?>" width="16" height="16" alt="<?php echo $this->_AppUI->_( 'next' );?>" border="0"></a></td>
	</tr>
	</table>
</form>
<table cellspacing="1" cellpadding="2" border="0" class="std" width="100%">
<?php
	if(!isset($people)){
?>
	<tr><td align="centre"><?php echo $this->_AppUI->_('No Users Available')?></td></tr>
<?php
	} else {
?>
<tr>
	<th><?php echo $this->_AppUI->_('User')?></th>
	<th><?php echo $this->_AppUI->_('Entity')?></th>
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
	if(isset($people))
	foreach($people as $id => $person){
?>
<tr>
	<td nowrap="nowrap"><?php echo $person['name']?></td>
	<td nowrap="nowrap"><?php echo $person['company_name']?></td>
<?php
	for($i=$week_count-1;$i>=0;$i--){
		 $hours = isset($person[$i])?$person[$i]:0;
		 $hours = round($hours,2);
		 if ($i==0 AND $hours == $person['user_id']) {
		   $hours = 0;
		 }
?>
	<td align="right"<?php echo $show_possible_hours_worked&&$hours>$min_hours_week?"bgcolor=\"#FFAEB8\"":""?>><a href="?m=timecard&user_id=<?php echo $id?>&tab=0&start_date=<?php echo $start_data_linkable[$i]?>"><?php echo number_format($hours,2)?></a></td>
<?php
	}
?>
</tr>
<?php
	}
}
?>
</table>
