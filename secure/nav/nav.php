<!doctype html>
<html>
<head>
<meta charset="utf-8">
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

<!-- Sidebar -->
<div class="sidebar">
  <a href="/secure/index.php" class="homebutton"> <i class="fa-solid fa-home fa-lg"></i> </a>
  <a href="/secure/search.php" class="sidebarbutton sidebarbutton-search"> <i class="fa-solid fa-magnifying-glass"></i> </a>
  <a href="/secure/new_incident.php?mode=firstline" class="sidebarbutton sidebarbutton-incident"> <i class="fa-solid fa-phone"></i> </a>
  <a href="/secure/new_change.php" class="sidebarbutton sidebarbutton-change"> <i class="fa-solid fa-pen"></i> </a>
  <a href="/secure/new_problem.php" class="sidebarbutton sidebarbutton-problem"> <i class="fa-solid fa-triangle-exclamation"></i> </a>
  <a href="/secure/new_ubm_item.php?type=initiative" class="sidebarbutton sidebarbutton-initiative"> <i class="fa-solid fa-lightbulb"></i> </a>
</div>
