<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="./include/style.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v7.2.0/css/all.css">
<title>ITSM</title>
</head>

<body>
<div class="layout">

<!-- Top bar -->
<div class="topbar">
  <div class="topbar-right"> Ingelogde behandelaar: <?php echo $_SESSION['name'];?> | <a href="./logout.php">Logout <i class="fa-solid fa-right-from-bracket"></i></a> </div>
</div>

<!-- Secure page tabs -->
<div class="secure-tabbar-wrap">
  <div class="secure-tabbar" id="secure_tabbar" aria-label="<?= htmlspecialchars(t('Open tabbladen')) ?>"></div>
</div>

<!-- Sidebar -->
<div class="sidebar">
  <a href="/secure/search.php" class="sidebarbutton sidebarbutton-search"> <i class="fa-solid fa-magnifying-glass"></i> </a>
  <a href="/secure/callercard.php" class="sidebarbutton sidebarbutton-caller">
    <span class="fa-stack fa-sm">
      <i class="fa-solid fa-globe fa-stack-2x"></i>
      <i class="fa-solid fa-phone fa-stack-1x sidebarbutton-caller-phone"></i>
    </span>
  </a>
  <a href="/secure/new_incident.php?mode=firstline" class="sidebarbutton sidebarbutton-incident"> <i class="fa-solid fa-phone"></i> </a>
  <a href="/secure/new_change.php" class="sidebarbutton sidebarbutton-change"> <i class="fa-solid fa-pen"></i> </a>
  <a href="/secure/new_problem.php" class="sidebarbutton sidebarbutton-problem"> <i class="fa-solid fa-triangle-exclamation"></i> </a>
  <a href="/secure/new_ubm_item.php?type=initiative" class="sidebarbutton sidebarbutton-initiative"> <i class="fa-solid fa-lightbulb"></i> </a>
  <a href="/secure/new_kb_item.php" class="sidebarbutton sidebarbutton-knowledge">
    <span class="fa-stack fa-sm">
      <i class="fa-solid fa-book fa-stack-2x"></i>
      <i class="fa-solid fa-info fa-stack-1x sidebarbutton-knowledge-info"></i>
    </span>
  </a>
</div>
