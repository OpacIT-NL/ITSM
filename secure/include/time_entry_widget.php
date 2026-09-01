<?php
require_once(__DIR__.'/time_helpers.php');
if(!isset($time_task_type,$time_task_id)||!in_array($time_task_type,time_allowed_task_types(),true))return;
$time_total=time_get_summary($con,$time_task_type,(int)$time_task_id); $time_codes=time_get_codes($con); $time_widget_id='time_'.preg_replace('/[^a-z0-9_]/i','',$time_task_type).'_'.(int)$time_task_id;
?>
<div class="task-time-row" id="<?= htmlspecialchars($time_widget_id) ?>" data-task-type="<?= htmlspecialchars($time_task_type) ?>" data-task-id="<?= (int)$time_task_id ?>" data-csrf="<?= htmlspecialchars(time_csrf_token()) ?>">
 <button type="button" class="task-time-add" <?= !$time_codes?'disabled':'' ?>><i class="fa-regular fa-clock"></i> Tijd toevoegen</button>
 <div class="task-time-total"><span>Totale tijd besteed</span><strong data-time-total><?= htmlspecialchars(time_format_minutes($time_total)) ?></strong></div>
 <dialog class="task-time-dialog">
  <div class="task-time-dialog-head"><h3>Tijd registreren</h3><button type="button" class="task-time-close" aria-label="Sluiten">×</button></div>
  <?php if(!$time_codes): ?><p>Er zijn nog geen actieve tijdcodes. Voeg deze toe via Instellingen.</p><?php else: ?>
  <div class="task-time-fields">
   <label>Tijdcode<select data-time-code required><option value="">Selecteer een tijdcode</option><?php foreach($time_codes as $code): ?><option value="<?= (int)$code['id'] ?>"><?= htmlspecialchars($code['code'].' - '.$code['name']) ?></option><?php endforeach; ?></select></label>
   <label>Datum<input type="date" data-time-date value="<?= htmlspecialchars(date('Y-m-d')) ?>" required></label>
   <label>Uren<input type="number" data-time-hours min="0" max="23" value="0" required></label>
   <label>Minuten<input type="number" data-time-minutes min="0" max="59" step="1" value="15" required></label>
   <label class="task-time-notes">Notitie<textarea data-time-notes maxlength="500"></textarea></label>
  </div>
  <p class="task-time-error" hidden></p><div class="task-time-actions"><button type="button" class="task-time-close">Annuleren</button><button type="button" class="task-time-save">Tijd registreren</button></div>
  <?php endif; ?>
 </dialog>
</div>
<script>
(function(){
 const root=document.getElementById(<?= json_encode($time_widget_id) ?>);if(!root||root.dataset.ready)return;root.dataset.ready='1';
 const dialog=root.querySelector('dialog'),open=root.querySelector('.task-time-add');
 root.querySelectorAll('.task-time-close').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 if(open)open.addEventListener('click',()=>dialog.showModal());
 const save=root.querySelector('.task-time-save');if(!save)return;
 save.addEventListener('click',async()=>{const error=root.querySelector('.task-time-error');error.hidden=true;save.disabled=true;
  const data=new FormData();data.set('task_type',root.dataset.taskType);data.set('task_id',root.dataset.taskId);data.set('_csrf_token',root.dataset.csrf);data.set('time_code_id',root.querySelector('[data-time-code]').value);data.set('work_date',root.querySelector('[data-time-date]').value);data.set('hours',root.querySelector('[data-time-hours]').value);data.set('minutes',root.querySelector('[data-time-minutes]').value);data.set('notes',root.querySelector('[data-time-notes]').value);
  try{const response=await fetch('add_time_entry.php',{method:'POST',body:data,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'Registreren is mislukt.');root.querySelector('[data-time-total]').textContent=result.total_display;dialog.close();}
  catch(e){error.textContent=e.message;error.hidden=false;}finally{save.disabled=false;}
 });
})();
</script>
