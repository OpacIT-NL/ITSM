<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once( __DIR__ . '/../my.php' );
if(!isset($_SESSION['operatorloggedin'])){header('Location: login.php');exit;}
$stmt=mysqli_prepare($con,'SELECT isadmin FROM itsm_ob_operators WHERE username=?');mysqli_stmt_bind_param($stmt,'s',$_SESSION['name']);mysqli_stmt_execute($stmt);$admin=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);if(!$admin||(int)$admin['isadmin']!==1){header('Location: index.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 if($action==='save'){
  $code=strtoupper(trim((string)($_POST['code']??'')));$name=trim((string)($_POST['name']??''));$sort=(int)($_POST['sortorder']??0);$active=isset($_POST['active'])?1:0;
  if(!preg_match('/^[A-Z0-9_-]{1,32}$/',$code)||$name==='')$error='Vul een geldige code en naam in.';
  elseif($id){$save=mysqli_prepare($con,'UPDATE itsm_core_timecodes SET code=?,name=?,active=?,sortorder=? WHERE id=?');mysqli_stmt_bind_param($save,'ssiii',$code,$name,$active,$sort,$id);if(!mysqli_stmt_execute($save))$error=mysqli_stmt_errno($save)===1062?'De code bestaat al.':itsm_error_reference('timecode_update_failed',mysqli_stmt_error($save));mysqli_stmt_close($save);}
  else{$save=mysqli_prepare($con,'INSERT INTO itsm_core_timecodes (code,name,active,sortorder) VALUES (?,?,?,?)');mysqli_stmt_bind_param($save,'ssii',$code,$name,$active,$sort);if(!mysqli_stmt_execute($save))$error=mysqli_stmt_errno($save)===1062?'De code bestaat al.':itsm_error_reference('timecode_insert_failed',mysqli_stmt_error($save));mysqli_stmt_close($save);}
 }elseif($action==='toggle'&&$id){$toggle=mysqli_prepare($con,'UPDATE itsm_core_timecodes SET active=1-active WHERE id=?');mysqli_stmt_bind_param($toggle,'i',$id);if(!mysqli_stmt_execute($toggle))$error=itsm_error_reference('timecode_toggle_failed',mysqli_stmt_error($toggle));mysqli_stmt_close($toggle);}
 if($error===''){header('Location: set-timecodes.php');exit;}
}
$codes_result=mysqli_query($con,'SELECT * FROM itsm_core_timecodes ORDER BY sortorder ASC,name ASC');$codes=$codes_result?mysqli_fetch_all($codes_result,MYSQLI_ASSOC):[];
?>
<?php require_once(__DIR__.'/nav/nav.php');require_once(__DIR__.'/nav/settings.php'); ?>
<div class="module-section"><h1>Tijdcodes</h1><?php if($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
 <div class="timecode-grid"><div class="report-panel"><h2>Nieuwe tijdcode</h2><form method="post" class="record-form"><input type="hidden" name="action" value="save"><label>Code<input name="code" maxlength="32" required placeholder="WERK"></label><label>Naam<input name="name" maxlength="255" required></label><label>Volgorde<input type="number" name="sortorder" value="0"></label><label><input type="checkbox" name="active" checked> Actief</label><button type="submit">Toevoegen</button></form></div>
 <div class="report-panel"><h2>Bestaande tijdcodes</h2><div class="results"><table><thead><tr><th>Code</th><th>Naam</th><th>Volgorde</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($codes as $code): $form_id='timecode_'.(int)$code['id']; ?><tr><td><input form="<?= $form_id ?>" name="code" value="<?= htmlspecialchars($code['code']) ?>" required></td><td><input form="<?= $form_id ?>" name="name" value="<?= htmlspecialchars($code['name']) ?>" required></td><td><input form="<?= $form_id ?>" type="number" name="sortorder" value="<?= (int)$code['sortorder'] ?>"></td><td><label><input form="<?= $form_id ?>" type="checkbox" name="active" <?= (int)$code['active']===1?'checked':'' ?>> Actief</label></td><td><form method="post" id="<?= $form_id ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$code['id'] ?>"><button type="submit">Opslaan</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div></div><?php require_once(__DIR__.'/nav/end.php'); ?>
