<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/time_helpers.php' );
header('Content-Type: application/json; charset=UTF-8');
function time_json_error($message,$status=400){http_response_code($status);echo json_encode(['ok'=>false,'error'=>$message]);exit;}
if(!isset($_SESSION['operatorloggedin'],$_SESSION['id']))time_json_error('Niet ingelogd.',401);
$task_type=(string)($_POST['task_type']??'');$task_id=filter_var($_POST['task_id']??null,FILTER_VALIDATE_INT);$code_id=filter_var($_POST['time_code_id']??null,FILTER_VALIDATE_INT);
$hours=filter_var($_POST['hours']??0,FILTER_VALIDATE_INT);$minutes=filter_var($_POST['minutes']??0,FILTER_VALIDATE_INT);$work_date=(string)($_POST['work_date']??'');$notes=trim((string)($_POST['notes']??''));
if(!in_array($task_type,time_allowed_task_types(),true)||!$task_id||$task_id<1)time_json_error('Ongeldige taak.');
$permission_stmt=mysqli_prepare($con,'SELECT firstlineincidents,secondlineincidents,reqforchange,simplechange,extchange,problems,events,ubm,isadmin FROM itsm_ob_operators WHERE id=? LIMIT 1');
$session_operator_id=(int)$_SESSION['id'];mysqli_stmt_bind_param($permission_stmt,'i',$session_operator_id);mysqli_stmt_execute($permission_stmt);$permissions=mysqli_fetch_assoc(mysqli_stmt_get_result($permission_stmt));mysqli_stmt_close($permission_stmt);
$allowed=$permissions&&((int)$permissions['isadmin']===1||($task_type==='incident'&&((int)$permissions['firstlineincidents']===1||(int)$permissions['secondlineincidents']===1))||(in_array($task_type,['change','changeactivity'],true)&&((int)$permissions['reqforchange']===1||(int)$permissions['simplechange']===1||(int)$permissions['extchange']===1))||($task_type==='problem'&&(int)$permissions['problems']===1)||($task_type==='event'&&(int)$permissions['events']===1)||($task_type==='ubm'&&(int)$permissions['ubm']===1));
if(!$allowed)time_json_error('Geen toegang tot dit taaktype.',403);
$task_tables=['incident'=>'itsm_im_incidents','change'=>'itsm_cm_changes','changeactivity'=>'itsm_cm_changeactivities','problem'=>'itsm_pm_problems','event'=>'itsm_em_events','ubm'=>'itsm_ubm_items'];
$exists_stmt=mysqli_prepare($con,'SELECT id FROM '.$task_tables[$task_type].' WHERE id=? LIMIT 1');mysqli_stmt_bind_param($exists_stmt,'i',$task_id);mysqli_stmt_execute($exists_stmt);$task_exists=mysqli_fetch_assoc(mysqli_stmt_get_result($exists_stmt));mysqli_stmt_close($exists_stmt);if(!$task_exists)time_json_error('Taak niet gevonden.',404);
if($hours===false||$minutes===false||$hours<0||$hours>23||$minutes<0||$minutes>59||($hours===0&&$minutes===0))time_json_error('Voer een geldige tijd in.');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$work_date))time_json_error('Selecteer een geldige datum.');
$code_stmt=mysqli_prepare($con,'SELECT id FROM itsm_core_timecodes WHERE id=? AND active=1');mysqli_stmt_bind_param($code_stmt,'i',$code_id);mysqli_stmt_execute($code_stmt);$valid_code=mysqli_fetch_assoc(mysqli_stmt_get_result($code_stmt));mysqli_stmt_close($code_stmt);if(!$valid_code)time_json_error('Selecteer een actieve tijdcode.');
$total_minutes=($hours*60)+$minutes;$operator_id=(int)$_SESSION['id'];
$stmt=mysqli_prepare($con,'INSERT INTO itsm_core_timeentries (tasktype,taskid,timecodeid,operatorid,minutes,workdate,notes) VALUES (?,?,?,?,?,?,?)');mysqli_stmt_bind_param($stmt,'siiiiss',$task_type,$task_id,$code_id,$operator_id,$total_minutes,$work_date,$notes);if(!mysqli_stmt_execute($stmt))time_json_error(itsm_error_reference('time_entry_insert_failed',mysqli_stmt_error($stmt)),500);mysqli_stmt_close($stmt);
$total=time_get_summary($con,$task_type,$task_id);echo json_encode(['ok'=>true,'total_minutes'=>$total,'total_display'=>time_format_minutes($total)]);
