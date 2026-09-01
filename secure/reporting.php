<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once(__DIR__.'/../my.php');
require_once(__DIR__.'/include/reporting_helpers.php');
require_once(__DIR__.'/include/time_helpers.php');
if(!isset($_SESSION['operatorloggedin'])){header('Location: login.php');exit;}
reporting_require_access($con,$_SESSION['name']);
$catalog=reporting_catalog(); $module=$_GET['module']??'incidents'; if(!isset($catalog[$module]))$module='incidents';
$definition=$catalog[$module]; $filters=reporting_filters($definition); [$where,$types,$params]=reporting_where($definition,$filters); $dimension=$filters['group_by'];
try{
  $metric=($definition['metric']??'count')==='minutes'?'SUM(q.minuten)':'COUNT(*)';
  $summary=reporting_query($con,"SELECT COALESCE(q.`$dimension`,'Onbekend') label,$metric aantal FROM ({$definition['sql']}) q$where GROUP BY q.`$dimension` ORDER BY aantal DESC,label ASC",$types,$params);
  $total=array_sum(array_map(fn($row)=>(int)$row['aantal'],$summary));
  $detail_sql="SELECT * FROM ({$definition['sql']}) q$where ORDER BY q.nummer DESC"; if(($_GET['export']??'')!=='csv')$detail_sql.=' LIMIT 500';
  $rows=reporting_query($con,$detail_sql,$types,$params);
}catch(Throwable $error){itsm_fail('report_build_failed',$error->getMessage(),500,$error);}
if(($_GET['export']??'')==='csv'){
  header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="rapport-'.$module.'-'.date('Y-m-d').'.csv"');
  $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,array_values($definition['columns']),';');
  foreach($rows as $row){$line=[];foreach($definition['columns'] as $key=>$label)$line[]=reporting_csv_value($row[$key]??'');fputcsv($out,$line,';');} fclose($out);exit;
}
$is_time_report=($definition['metric']??'count')==='minutes';$max_count=empty($summary)?1:max(array_map(fn($row)=>(int)$row['aantal'],$summary));
?>
<?php require_once(__DIR__.'/nav/nav.php'); ?>
<div class="content reporting-page" data-tab-title="Rapportages" data-tab-subtitle="<?= htmlspecialchars($definition['label']) ?>">
 <?php $module_back_url='modules.php';require(__DIR__.'/include/module_links.php'); ?>
 <h1>Rapportages</h1>
 <div class="report-layout">
  <aside class="report-catalog">
   <?php foreach(['Operationeel','Ondersteunende bestanden'] as $section): ?><h2><?= htmlspecialchars($section) ?></h2>
    <?php foreach($catalog as $key=>$item):if($item['section']!==$section)continue; ?><a class="<?= $module===$key?'active':'' ?>" href="reporting.php?module=<?= urlencode($key) ?>"><?= htmlspecialchars($item['label']) ?></a><?php endforeach; ?>
   <?php endforeach; ?>
  </aside>
  <main class="report-main">
   <div class="report-panel"><h2><?= htmlspecialchars($definition['label']) ?></h2><p><?= $is_time_report?htmlspecialchars(time_format_minutes($total)):$total.' records' ?> in de huidige selectie</p>
    <form method="get" class="report-filters"><input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
     <?php if($definition['date']): ?><label>Vanaf<input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>"></label><label>Tot en met<input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>"></label><?php endif; ?>
     <label>Groepeer op<select name="group_by"><?php foreach($definition['dimensions'] as $key=>$label): ?><option value="<?= htmlspecialchars($key) ?>" <?= $dimension===$key?'selected':'' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
     <button type="submit">Toepassen</button><a href="reporting.php?module=<?= urlencode($module) ?>">Wissen</a><button type="submit" name="export" value="csv" class="secondary">CSV exporteren</button>
    </form>
   </div>
   <div class="report-kpis"><div><span>Totaal</span><strong><?= $is_time_report?htmlspecialchars(time_format_minutes($total)):$total ?></strong></div><div><span>Groepen</span><strong><?= count($summary) ?></strong></div><div><span>Grootste groep</span><strong><?= $is_time_report?htmlspecialchars(time_format_minutes($max_count)):$max_count ?></strong></div></div>
   <div class="report-panel"><h2>Verdeling op <?= htmlspecialchars(strtolower($definition['dimensions'][$dimension])) ?></h2><div class="report-bars">
    <?php foreach($summary as $item):$width=round(((int)$item['aantal']/$max_count)*100,1); ?><div class="report-bar-row"><span title="<?= htmlspecialchars($item['label']) ?>"><?= htmlspecialchars($item['label']) ?></span><div><i style="width:<?= $width ?>%"></i></div><strong><?= $is_time_report?htmlspecialchars(time_format_minutes((int)$item['aantal'])):(int)$item['aantal'] ?></strong></div><?php endforeach; ?>
    <?php if(!$summary): ?><p>Geen gegevens voor deze selectie.</p><?php endif; ?>
   </div></div>
   <div class="report-panel"><h2>Details <?= count($rows)===500?'(eerste 500)':'' ?></h2><div class="results report-results"><table><thead><tr><?php foreach($definition['columns'] as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($definition['columns'] as $key=>$label): ?><td><?= htmlspecialchars((string)($row[$key]??'')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div>
  </main>
 </div>
</div>
<?php require_once(__DIR__.'/nav/end.php'); ?>
