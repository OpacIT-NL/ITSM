<?php

function time_allowed_task_types() {
  return ['incident','change','changeactivity','problem','event','ubm'];
}

function time_format_minutes($minutes) {
  $minutes=max(0,(int)$minutes); $hours=intdiv($minutes,60); $remainder=$minutes%60;
  return $hours > 0 ? $hours.'u '.str_pad((string)$remainder,2,'0',STR_PAD_LEFT).'m' : $remainder.'m';
}

function time_get_summary($con,$task_type,$task_id) {
  $stmt=mysqli_prepare($con,'SELECT COALESCE(SUM(minutes),0) total FROM itsm_core_timeentries WHERE tasktype=? AND taskid=?');
  mysqli_stmt_bind_param($stmt,'si',$task_type,$task_id); mysqli_stmt_execute($stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
  return (int)($row['total']??0);
}

function time_get_codes($con) {
  $result=mysqli_query($con,'SELECT id,code,name FROM itsm_core_timecodes WHERE active=1 ORDER BY sortorder ASC,name ASC');
  return $result?mysqli_fetch_all($result,MYSQLI_ASSOC):[];
}

function time_csrf_token() {
  return itsm_csrf_token();
}
