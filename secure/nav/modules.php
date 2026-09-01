
<div class="content">
<div class="page-layout">
<div class="page-sidebar">
  <center>
    <h1><?= htmlspecialchars(t('Modules')) ?></h1>
  </center>
  <div class="modules-menu"> <a href="./ob-menu.php"><?= htmlspecialchars(t('Ondersteunende Bestanden')) ?></a> <a href="./am-menu.php"><?= htmlspecialchars(t('Asset Management')) ?></a> <a href="./im-menu.php"><?= htmlspecialchars(t('Incident Management')) ?></a> <a href="./cm-menu.php"><?= htmlspecialchars(t('Change Management')) ?></a> <a href="./pm-menu.php"><?= htmlspecialchars(t('Problem Management')) ?></a> <a href="./em-menu.php"><?= htmlspecialchars(t('Event Management')) ?></a> <a href="./ubm-menu.php"><?= htmlspecialchars(t('Universal Backlog Management')) ?></a> <a href="./kb-menu.php"><?= htmlspecialchars(t('Kennisbank')) ?></a> <a href="./news-menu.php"><?= htmlspecialchars(t('Nieuws')) ?></a> 
    <!-- Future modules --> 
    <!--
    <a href="./inc-menu.php">Incident Management</a>
    
    --> 
    
    <?php
    $show_reporting = false;
    if (isset($con, $_SESSION['name'])) {
      $reporting_menu_stmt = mysqli_prepare($con, 'SELECT reporting, isadmin FROM itsm_ob_operators WHERE username = ? LIMIT 1');
      mysqli_stmt_bind_param($reporting_menu_stmt, 's', $_SESSION['name']);
      mysqli_stmt_execute($reporting_menu_stmt);
      $reporting_menu_row = mysqli_fetch_assoc(mysqli_stmt_get_result($reporting_menu_stmt));
      mysqli_stmt_close($reporting_menu_stmt);
      $show_reporting = $reporting_menu_row && ((int)$reporting_menu_row['reporting'] === 1 || (int)$reporting_menu_row['isadmin'] === 1);
    }
    ?>
    <?php if ($show_reporting): ?><a href="./reporting.php"><?= htmlspecialchars(t('Rapportages')) ?></a><?php endif; ?>
  </div>
</div>
<div class="page-content">
