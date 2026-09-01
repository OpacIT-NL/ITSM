<?php

function reporting_support_definition($label, $sql, $dimensions, $columns) {
  return ['label'=>$label, 'section'=>'Ondersteunende bestanden', 'date'=>false, 'sql'=>$sql, 'dimensions'=>$dimensions, 'columns'=>$columns];
}

function reporting_catalog() {
  $operational = [
    'incidents' => [
      'label'=>'Incidenten', 'sql'=>"SELECT i.incidentnumber nummer,i.title titel,COALESCE(s.name,'Geen status') status,COALESCE(cat.name,'Geen categorie') categorie,COALESCE(g.groupname,'Niet toegewezen') team,COALESCE(c.name,'Geen klant') klant,i.createdat aangemaakt,i.updatedat bijgewerkt FROM itsm_im_incidents i LEFT JOIN itsm_core_status s ON s.id=i.statusid LEFT JOIN itsm_core_category cat ON cat.id=i.categoryid LEFT JOIN itsm_ob_operatorgroups g ON g.id=i.operatorgroupid LEFT JOIN itsm_ob_customers c ON c.id=i.customerid",
      'dimensions'=>['status'=>'Status','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant'], 'columns'=>['nummer'=>'Nummer','titel'=>'Titel','status'=>'Status','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ],
    'changes' => [
      'label'=>'Wijzigingen', 'sql'=>"SELECT c.changenumber nummer,c.title titel,CASE c.approvalstate WHEN 'request' THEN 'Aanvraag' WHEN 'approved' THEN 'Goedgekeurd' WHEN 'rejected' THEN 'Afgewezen' ELSE c.approvalstate END status,COALESCE(cat.name,'Geen categorie') categorie,COALESCE(g.groupname,'Niet toegewezen') team,COALESCE(cu.name,'Geen klant') klant,c.createdat aangemaakt,c.updatedat bijgewerkt FROM itsm_cm_changes c LEFT JOIN itsm_core_category cat ON cat.id=c.categoryid LEFT JOIN itsm_ob_operatorgroups g ON g.id=c.operatorgroupid LEFT JOIN itsm_ob_customers cu ON cu.id=c.customerid",
      'dimensions'=>['status'=>'Fase','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant'], 'columns'=>['nummer'=>'Nummer','titel'=>'Titel','status'=>'Fase','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ],
    'change_activities' => [
      'label'=>'Wijzigingsactiviteiten', 'sql'=>"SELECT a.activitynumber nummer,a.title titel,COALESCE(s.name,'Geen status') status,COALESCE(g.groupname,'Niet toegewezen') team,COALESCE(c.changenumber,'-') wijziging,a.createdat aangemaakt,a.updatedat bijgewerkt FROM itsm_cm_changeactivities a LEFT JOIN itsm_core_status s ON s.id=a.statusid LEFT JOIN itsm_ob_operatorgroups g ON g.id=a.operatorgroupid LEFT JOIN itsm_cm_changes c ON c.id=a.changeid",
      'dimensions'=>['status'=>'Status','team'=>'Team','wijziging'=>'Wijziging'], 'columns'=>['nummer'=>'Nummer','titel'=>'Titel','wijziging'=>'Wijziging','status'=>'Status','team'=>'Team','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ],
    'problems' => [
      'label'=>'Problemen', 'sql'=>"SELECT p.problemnumber nummer,p.title titel,COALESCE(s.name,'Geen status') status,COALESCE(cat.name,'Geen categorie') categorie,COALESCE(g.groupname,'Niet toegewezen') team,COALESCE(c.name,'Geen klant') klant,p.createdat aangemaakt,p.updatedat bijgewerkt FROM itsm_pm_problems p LEFT JOIN itsm_core_status s ON s.id=p.statusid LEFT JOIN itsm_core_category cat ON cat.id=p.categoryid LEFT JOIN itsm_ob_operatorgroups g ON g.id=p.operatorgroupid LEFT JOIN itsm_ob_customers c ON c.id=p.customerid",
      'dimensions'=>['status'=>'Status','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant'], 'columns'=>['nummer'=>'Nummer','titel'=>'Titel','status'=>'Status','categorie'=>'Categorie','team'=>'Team','klant'=>'Klant','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ],
    'events' => [
      'label'=>'Events', 'sql'=>"SELECT e.eventnumber nummer,LEFT(e.description,160) titel,CASE WHEN e.closed=1 THEN 'Gesloten' WHEN e.acknowledged=1 THEN 'Bevestigd' ELSE 'Open' END status,COALESCE(cat.name,'Geen categorie') categorie,COALESCE(a.objectid,'Geen asset') asset,e.createdat aangemaakt,e.updatedat bijgewerkt FROM itsm_em_events e LEFT JOIN itsm_core_category cat ON cat.id=e.categoryid LEFT JOIN itsm_am_assets a ON a.id=e.assetid",
      'dimensions'=>['status'=>'Status','categorie'=>'Categorie','asset'=>'Asset'], 'columns'=>['nummer'=>'Nummer','titel'=>'Omschrijving','status'=>'Status','categorie'=>'Categorie','asset'=>'Asset','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ],
    'backlog' => [
      'label'=>'Universal Backlog', 'sql'=>"SELECT u.ubmnumber nummer,u.title titel,COALESCE(s.name,'Geen status') status,u.itemtype type,COALESCE(cat.name,'Geen categorie') categorie,COALESCE(g.groupname,'Niet toegewezen') team,u.createdat aangemaakt,u.updatedat bijgewerkt FROM itsm_ubm_items u LEFT JOIN itsm_core_status s ON s.id=u.statusid LEFT JOIN itsm_core_category cat ON cat.id=u.categoryid LEFT JOIN itsm_ob_operatorgroups g ON g.id=u.operatorgroupid",
      'dimensions'=>['status'=>'Status','type'=>'Type','categorie'=>'Categorie','team'=>'Team'], 'columns'=>['nummer'=>'Nummer','titel'=>'Titel','type'=>'Type','status'=>'Status','categorie'=>'Categorie','team'=>'Team','aangemaakt'=>'Aangemaakt','bijgewerkt'=>'Bijgewerkt']
    ]
  ];
  foreach ($operational as &$item) { $item['section']='Operationeel'; $item['date']=true; } unset($item);
  $operational['time_entries'] = [
    'label'=>'Tijdregistraties','section'=>'Operationeel','date'=>true,'metric'=>'minutes',
    'sql'=>"SELECT te.id nummer,CASE te.tasktype WHEN 'incident' THEN 'Incident' WHEN 'change' THEN 'Wijziging' WHEN 'changeactivity' THEN 'Wijzigingsactiviteit' WHEN 'problem' THEN 'Probleem' WHEN 'event' THEN 'Event' WHEN 'ubm' THEN 'Backlog' ELSE te.tasktype END titel,te.tasktype taaktype,CONCAT(o.lastname,', ',o.firstname) behandelaar,CONCAT(tc.code,' - ',tc.name) tijdcode,COALESCE(s.name,CASE WHEN te.tasktype='event' THEN 'Niet van toepassing' ELSE 'Geen status' END) status,COALESCE(g.groupname,CASE WHEN te.tasktype='event' THEN 'Niet van toepassing' ELSE 'Niet toegewezen' END) team,te.minutes minuten,te.workdate aangemaakt,te.notes notitie FROM itsm_core_timeentries te JOIN itsm_core_timecodes tc ON tc.id=te.timecodeid JOIN itsm_ob_operators o ON o.id=te.operatorid LEFT JOIN itsm_im_incidents i ON te.tasktype='incident' AND i.id=te.taskid LEFT JOIN itsm_cm_changes c ON te.tasktype='change' AND c.id=te.taskid LEFT JOIN itsm_cm_changeactivities ca ON te.tasktype='changeactivity' AND ca.id=te.taskid LEFT JOIN itsm_pm_problems p ON te.tasktype='problem' AND p.id=te.taskid LEFT JOIN itsm_ubm_items u ON te.tasktype='ubm' AND u.id=te.taskid LEFT JOIN itsm_core_status s ON s.id=COALESCE(i.statusid,c.statusid,ca.statusid,p.statusid,u.statusid) LEFT JOIN itsm_ob_operatorgroups g ON g.id=COALESCE(i.operatorgroupid,c.operatorgroupid,ca.operatorgroupid,p.operatorgroupid,u.operatorgroupid)",
    'dimensions'=>['behandelaar'=>'Behandelaar','tijdcode'=>'Tijdcode','taaktype'=>'Taaktype','team'=>'Team','status'=>'Status'],
    'columns'=>['nummer'=>'ID','titel'=>'Taaktype','behandelaar'=>'Behandelaar','tijdcode'=>'Tijdcode','team'=>'Team','status'=>'Status','minuten'=>'Minuten','aangemaakt'=>'Werkdatum','notitie'=>'Notitie']
  ];
  $operational['assets'] = ['label'=>'Assets','section'=>'Operationeel','date'=>false,'sql'=>"SELECT a.objectid nummer,COALESCE(t.type,'Geen type') titel,CASE WHEN a.archived=1 THEN 'Gearchiveerd' WHEN a.active=1 THEN 'Actief' ELSE 'Inactief' END status,COALESCE(t.type,'Geen type') type,COALESCE(CONCAT(p.lastname,', ',p.firstname),'Geen eigenaar') eigenaar,a.startdate startdatum,a.enddate einddatum FROM itsm_am_assets a LEFT JOIN itsm_am_types t ON t.id=a.type LEFT JOIN itsm_ob_persons p ON p.id=a.owner",'dimensions'=>['status'=>'Status','type'=>'Type','eigenaar'=>'Eigenaar'],'columns'=>['nummer'=>'Object-ID','titel'=>'Type','status'=>'Status','eigenaar'=>'Eigenaar','startdatum'=>'Startdatum','einddatum'=>'Einddatum']];
  return $operational + [
    'persons'=>reporting_support_definition('Personen',"SELECT p.id nummer,CONCAT(p.lastname,', ',p.firstname) titel,CASE WHEN p.allowssp=1 THEN 'Portaaltoegang' ELSE 'Geen portaaltoegang' END status,COALESCE(c.name,'Geen klant') klant,p.email email,p.phone telefoon FROM itsm_ob_persons p LEFT JOIN itsm_ob_customers c ON c.id=p.customerid",['status'=>'Toegang','klant'=>'Klant'],['nummer'=>'ID','titel'=>'Naam','status'=>'Toegang','klant'=>'Klant','email'=>'E-mail','telefoon'=>'Telefoon']),
    'person_groups'=>reporting_support_definition('Persoonsgroepen',"SELECT pg.id nummer,pg.groupname titel,'Persoonsgroep' status,COUNT(l.id) leden FROM itsm_ob_persongroups pg LEFT JOIN itsm_ob_persongrouplinks l ON l.persongroup=pg.id GROUP BY pg.id,pg.groupname",['status'=>'Type'],['nummer'=>'ID','titel'=>'Naam','leden'=>'Leden']),
    'operators'=>reporting_support_definition('Behandelaars',"SELECT o.id nummer,CONCAT(o.lastname,', ',o.firstname) titel,CASE WHEN o.allowlogin=1 THEN 'Actief' ELSE 'Inactief' END status,o.username gebruikersnaam,o.email email FROM itsm_ob_operators o",['status'=>'Status'],['nummer'=>'ID','titel'=>'Naam','status'=>'Status','gebruikersnaam'=>'Gebruikersnaam','email'=>'E-mail']),
    'operator_groups'=>reporting_support_definition('Behandelaarsgroepen',"SELECT g.id nummer,g.groupname titel,'Behandelaarsgroep' status,COUNT(l.id) leden FROM itsm_ob_operatorgroups g LEFT JOIN itsm_ob_opgrouplinks l ON l.groupid=g.id GROUP BY g.id,g.groupname",['status'=>'Type'],['nummer'=>'ID','titel'=>'Naam','leden'=>'Leden']),
    'suppliers'=>reporting_support_definition('Leveranciers',"SELECT s.id nummer,s.name titel,'Leverancier' status,s.cin code,s.city plaats,s.primaryemail email FROM itsm_ob_suppliers s",['status'=>'Type','plaats'=>'Plaats'],['nummer'=>'ID','titel'=>'Naam','code'=>'Code','plaats'=>'Plaats','email'=>'E-mail']),
    'buildings'=>reporting_support_definition('Gebouwen',"SELECT b.id nummer,b.address titel,'Gebouw' status,COALESCE(c.name,'Geen klant') klant,b.postalcode postcode,b.city plaats FROM itsm_ob_buildings b LEFT JOIN itsm_ob_customers c ON c.id=b.customer",['status'=>'Type','klant'=>'Klant','plaats'=>'Plaats'],['nummer'=>'ID','titel'=>'Adres','klant'=>'Klant','postcode'=>'Postcode','plaats'=>'Plaats']),
    'customers'=>reporting_support_definition('Klanten',"SELECT c.id nummer,c.name titel,'Klant' status,c.din code,c.city plaats,c.primaryemail email FROM itsm_ob_customers c",['status'=>'Type','plaats'=>'Plaats'],['nummer'=>'ID','titel'=>'Naam','code'=>'Code','plaats'=>'Plaats','email'=>'E-mail'])
  ];
}

function reporting_require_access($con, $username) {
  $stmt=mysqli_prepare($con,'SELECT reporting,isadmin FROM itsm_ob_operators WHERE username=? LIMIT 1');
  mysqli_stmt_bind_param($stmt,'s',$username); mysqli_stmt_execute($stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
  if (!$row || ((int)$row['reporting']!==1 && (int)$row['isadmin']!==1)) { header('Location: modules.php'); exit; }
}

function reporting_filters($definition) {
  $from=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'')?$_GET['from']:''; $to=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'')?$_GET['to']:'';
  $dimension=$_GET['group_by']??array_key_first($definition['dimensions']); if(!isset($definition['dimensions'][$dimension]))$dimension=array_key_first($definition['dimensions']);
  return ['from'=>$from,'to'=>$to,'group_by'=>$dimension];
}

function reporting_where($definition,$filters) {
  $where=[];$params=[];$types='';
  if($definition['date']&&$filters['from']!==''){$where[]='q.aangemaakt >= ?';$params[]=$filters['from'].' 00:00:00';$types.='s';}
  if($definition['date']&&$filters['to']!==''){$where[]='q.aangemaakt < DATE_ADD(?, INTERVAL 1 DAY)';$params[]=$filters['to'].' 00:00:00';$types.='s';}
  return [$where?' WHERE '.implode(' AND ',$where):'',$types,$params];
}

function reporting_query($con,$sql,$types='',$params=[]) {
  $stmt=mysqli_prepare($con,$sql); if(!$stmt)throw new RuntimeException(mysqli_error($con));
  if($types!=='')mysqli_stmt_bind_param($stmt,$types,...$params); mysqli_stmt_execute($stmt); $rows=mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC); mysqli_stmt_close($stmt); return $rows;
}

function reporting_csv_value($value) {
  $value=(string)$value;
  return preg_match('/^[=+\-@]/',$value) ? "'".$value : $value;
}
