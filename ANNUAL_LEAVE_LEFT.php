<?
include_once("inc/auth.inc.php");

function Duty_Total_Time($USER_ID,$begin_leave_time,$finish_leave_time,$LEAVE_TYPE){	//获取$start_leave_time到$end_leave_time之间的$USER_ID用户的请假各时间，不含公休及法定节假日
	$begin_leave_point=strtotime($begin_leave_time);   //提取请假开始时间转换为Unix时间格式
	$finish_leave_point=strtotime($finish_leave_time); //提取请假结束时间转换为Unix时间格式
	
	if ($begin_leave_point>$finish_leave_point)//请假开始时间若大于结束时间，则停止并返回-1
		die("-1");
	
	$all_minute=0;	//初始化总分钟数；
	
	$begin_time_array = explode(" ",$begin_leave_time); //通过空格，分拆日期和时间到数组
	$finish_time_array = explode(" ",$finish_leave_time);//通过空格，分拆日期和时间到数组
	
	$query="SELECT DUTY_TIME1,DUTY_TIME2,DUTY_TIME3,DUTY_TIME4,DUTY_TIME5,DUTY_TIME6,duty_date from user_duty,attend_config,USER where USER.USER_ID='$USER_ID' and USER.UID=user_duty.uid and attend_config.DUTY_TYPE=user_duty.duty_type and duty_date between '$begin_time_array[0]' and '$finish_time_array[0]' order by duty_date asc";//查找user_duty,attend_config表，建立$USER_ID用户的$begin_time_array[0]请假开始日期到$finish_time_array[0]结束日期之间的排班表，包含排班表中的开始与结束时间列和日期列
	$cursor = exequery(TD::conn(),$query); 
	
	$duty_day=mysql_num_rows($cursor);	//求出$duty_day请假时间段全部日期，不包含公休及法定节假日数，用于计算请假时间段的公休及法定节假日数
	
	while($row = MySQL_fetch_row($cursor)){ //循环提取返回的排班行，和请假开始与结束日期比较，若在排班段若在请假范围内，则累加排班段分钟数
		for($x=0;$x<=2;$x++){
			$start_duty_point=strtotime($row[6]." ".$row[$x*2]);//提取$row[6]即duty_date跟$row[$x*2]即DUTY_TIME开始时间，排班段开始时间转换为Unix时间格式
			$end_duty_point=strtotime($row[6]." ".$row[$x*2+1]);//提取$row[6]即duty_date跟$row[$x*2+1]即DUTY_TIME结束时间，排班段结束时间转换为Unix时间格式
			
			if($start_duty_point>$end_duty_point){	//判断若开始时间$start_duty_point大于结束时间$end_duty_point，则是跨天时间段，结束时间通过UNIX时间加1天来调整
				$end_duty_point+=86400;	//UNIX时间加1天
			}
			
			if($start_duty_point>=$begin_leave_point and $end_duty_point<=$finish_leave_point){		//排班时间段内涵在请假时间段内，计算排班时间段分钟数并四舍五入
				$all_minute+=round(($end_duty_point-$start_duty_point)/60);
			}
			elseif($start_duty_point<$begin_leave_point and $end_duty_point>$finish_leave_point){	//排班时间段包含在请假时间段外，计算请假时间段分钟数并四舍五入
				$all_minute+=round(($finish_leave_point-$begin_leave_point)/60);
			}
			elseif($end_duty_point>$begin_leave_point and $end_duty_point<$finish_leave_point){		// 排班结束时间内涵在请假时间段内，计算排班结束减去请假开始段分钟数并四舍五入
				$all_minute+=round(($end_duty_point-$begin_leave_point)/60);
			}
			elseif($start_duty_point>$begin_leave_point and $start_duty_point<$finish_leave_point){	// 排班开始时间内涵在请假时间段内，计算请假结束减去排班开始段分钟数并四舍五入
				$all_minute+=round(($finish_leave_point-$start_duty_point)/60);
			}
		}
	}
	$all_hour=ceil($all_minute/60);	//转换总分钟数为总小时数并向上取整
	$duty_total_day=floor($all_hour/8);	//求请假天数$duty_total_day，总小时数除以8
	$duty_total_hour=$all_hour%8;		//求请假小时$duty_total_hour，总小时数对8取余数
	
	if ($LEAVE_TYPE==='04'){	//若请假类型为04倒休，则求倒休整天数$duty_off_day
		$duty_off_day=ceil($all_hour/8);		//求请假倒休整天数$duty_off_day，总小时数除以8向上取整
	}
	else{
		$duty_off_day=0;
	}
	
	if ($LEAVE_TYPE==3){	//若请假类型为3年假，则求请假年假天数$duty_annual_day
		$duty_annual_day=$duty_total_day+ceil($duty_total_hour/4)/2;	//求请假年假天数$duty_annual_day，请假天数$duty_total_day加上（请假小时数$duty_total_hour除以4后，向上取整，再除以2求出半天数）
	}
	else{
		$duty_annual_day=0;
	}

	if($LEAVE_TYPE==2 or $LEAVE_TYPE==='4' or $LEAVE_TYPE==6 or $LEAVE_TYPE==8){	//若产假,计划生育假,病假,探亲假，则求公休及法定节假日，用于补充上$unduty_day请假时间段的公休及法定节假日数
		$query="SELECT uid from user_duty where uid=1 and duty_date between '$begin_time_array[0]' and '$finish_time_array[0]'";//查找user_duty表中$USER_ID=1的管理员应上班天数（预先设置了管理员全年均上班）
		$cursor = exequery(TD::conn(),$query); 	
		$unduty_day=mysql_num_rows($cursor)-$duty_day;	//求出$unduty_day请假时间段的公休及法定节假日数。请假时间段全部日期包含公休及法定节假日数，减去$duty_day员工出勤日期
		$duty_total_day+=$unduty_day;	//请假天数$duty_total_day补充上$unduty_day请假时间段的公休及法定节假日数
	}
	$results[0]=$duty_total_day;	//数组$results[0]，存入请假天数$duty_total_day
	$results[1]=$duty_total_hour;	//数组$results[1]，存入请假小时$duty_total_hour
	$results[2]=$duty_off_day;		//数组$results[2]，存入请假倒休整天数$duty_off_day
	$results[3]=$duty_annual_day;	//数组$results[3]，存入请假年假天数$duty_annual_day
	$results[4]=get_ann($USER_ID);	//数组$results[4]，存入可用年假天数，通过调用函数get_ann($USER_ID)
	$results[5]=get_off($USER_ID);	//数组$results[5]，存入可用倒休天数，通过调用函数get_ann($USER_ID)	
	echo json_encode($results);	//返回JSON数组

}
Duty_Total_Time('admin',"2018-01-05 03:00:00","2018-07-31 19:00:00",'04');

function get_ann($USER_ID) //获取年假函数
{
	$CUR_DATE = date("Y-m-d", time());
	$query = "SELECT LEAVE_TYPE from HR_STAFF_INFO where USER_ID='$USER_ID'";
	$cursor = exequery(TD::conn(), $query);

	if ($ROW = mysql_fetch_array($cursor)) {
		$LEAVE_TYPE1 = $ROW["LEAVE_TYPE"];
	}

	$query = "select * from SYS_PARA where PARA_NAME='ANNUAL_BEGIN_TIME'";
	$cursor = exequery(TD::conn(), $query);

	if ($ROW = mysql_fetch_array($cursor)) {
		$ANNUAL_BEGIN_TIME = $ROW["PARA_VALUE"];
	}

	$query = "select * from SYS_PARA where PARA_NAME='ANNUAL_END_TIME'";
	$cursor = exequery(TD::conn(), $query);

	if ($ROW = mysql_fetch_array($cursor)) {
		$ANNUAL_END_TIME = $ROW["PARA_VALUE"];
	}

	$CUR_YEAR = date("Y", time());
	$CUR_M = date("m", time());
	$ANNUAL_BEGIN_TIME_ARRAY = explode("-", $ANNUAL_BEGIN_TIME);

	if ($CUR_M < $ANNUAL_BEGIN_TIME_ARRAY[1]) {
		$CUR_YEAR1 = $CUR_YEAR - 1;
		$BEGIN_TIME = $CUR_YEAR1 . "$ANNUAL_BEGIN_TIME";
		$END_TIME = $CUR_YEAR . "$ANNUAL_END_TIME";
	}
	else {
		$BEGIN_TIME = $CUR_YEAR . "$ANNUAL_BEGIN_TIME";
		$END_TIME = $CUR_YEAR . "$ANNUAL_END_TIME";
	}

	$query = "SELECT * from ATTEND_LEAVE where USER_ID='$USER_ID' and (ALLOW='1' or ALLOW='3' or ALLOW='0') and LEAVE_DATE1 >='$BEGIN_TIME' and LEAVE_DATE1 <='$END_TIME'";
	$cursor = exequery(TD::conn(), $query);
	$LEAVE_DAYS = 0;
	$ANNUAL_LEAVE_DAYS = 0;

	while ($ROW = mysql_fetch_array($cursor)) {
		$LEAVE_DATE1 = $ROW["LEAVE_DATE1"];
		$LEAVE_DATE2 = $ROW["LEAVE_DATE2"];
		$ANNUAL_LEAVE = $ROW["ANNUAL_LEAVE"];
		$DAY_DIFF = DateDiff_("d", $LEAVE_DATE1, $LEAVE_DATE2);
		$LEAVE_DAYS += $DAY_DIFF;
		$LEAVE_DAYS = number_format($LEAVE_DAYS, 1, ".", " ");
		$ANNUAL_LEAVE_DAYS += $ANNUAL_LEAVE;
		$ANNUAL_LEAVE_DAYS = number_format($ANNUAL_LEAVE_DAYS, 1, ".", " ");
	}

	$ANNUAL_LEAVE_LEFT = number_format($LEAVE_TYPE1 - $ANNUAL_LEAVE_DAYS, 1, ".", " ");

	if ($ANNUAL_LEAVE_LEFT < 0) {
		$ANNUAL_LEAVE_LEFT = 0;
	}

	return $ANNUAL_LEAVE_LEFT;
}

function get_off($USER_ID)	 //获取倒休函数
{
	$ruel_num = 0;
	$query = "SELECT DEPT_ID from USER where USER_ID='" . $USER_ID . "'";
	$cursor = exequery(TD::conn(), $query);

	if ($ROW = mysql_fetch_array($cursor)) {
		$dept_id = $ROW["DEPT_ID"];
	}

	$query = "select overtime_hour from attend_rule where dept_id_str!='ALL_DEPT' and find_in_set('" . $dept_id . "',dept_id_str)";
	$cursor = exequery(TD::conn(), $query);

	if ($ROW = mysql_fetch_array($cursor)) {
		$over_hour = $ROW["overtime_hour"];
		$ruel_num++;
	}

	if (0 < $ruel_num) {
		$overtime_hour = $over_hour;
	}
	else {
		$sql = "select overtime_hour from attend_rule where dept_id_str='ALL_DEPT'";
		$result = exequery(TD::conn(), $sql);

		if ($ROW = mysql_fetch_array($result)) {
			$overtime_hour = $ROW["overtime_hour"];
		}
		else {
			$overtime_hour = 0;
		}
	}

	if ($overtime_hour != "0") {
		$query = "select OFF_DURATION from HR_STAFF_INFO where USER_ID='" . $USER_ID . "'";
		$cursor = exequery(TD::conn(), $query);

		if ($ROW = mysql_fetch_array($cursor)) {
			$OFF_DURATION = $ROW["OFF_DURATION"];
		}

		$off_arr = explode("_", $OFF_DURATION);

		if ($off_arr[0] != "") {
			$ANNUAL_OFF_LEFT = $off_arr[0];
		}
		else {
			$ANNUAL_OFF_LEFT = 0;
		}
	}
	else {
		$ANNUAL_OFF_LEFT = 0;
	}

	return $ANNUAL_OFF_LEFT;
}
?>




