<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/news_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
}

$logged_in_user = $_SESSION['name'];
news_require_admin( $con, $logged_in_user );
$news_id = (int)$_GET['id'];
$type_options = news_type_options();

$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_core_news WHERE id = ? LIMIT 1" );
mysqli_stmt_bind_param( $stmt, "i", $news_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$news_item = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$news_item ) {
  die( 'Nieuwsbericht niet gevonden' );
}

$errors = [];
$form_values = [
  'newstype' => $news_item['newstype'],
  'title' => $news_item['title'],
  'message' => $news_item['message'],
  'showssp' => (int)$news_item['showssp'],
  'showoperatorhome' => (int)$news_item['showoperatorhome'],
  'showlogin' => (int)$news_item['showlogin']
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'newstype' => $_POST['newstype'] ?? 'general',
    'title' => trim( $_POST['title'] ?? '' ),
    'message' => trim( $_POST['message'] ?? '' ),
    'showssp' => isset( $_POST['showssp'] ) ? 1 : 0,
    'showoperatorhome' => isset( $_POST['showoperatorhome'] ) ? 1 : 0,
    'showlogin' => isset( $_POST['showlogin'] ) ? 1 : 0
  ];

  if ( !isset( $type_options[ $form_values['newstype'] ] ) ) {
    $errors[] = 'Selecteer een geldig type nieuwsbericht.';
  }
  if ( $form_values['title'] === '' ) {
    $errors[] = 'Titel is verplicht.';
  }
  if ( $form_values['message'] === '' ) {
    $errors[] = 'Bericht is verplicht.';
  }

  if ( empty( $errors ) ) {
    $update_stmt = mysqli_prepare( $con, "
      UPDATE itsm_core_news
      SET newstype = ?, title = ?, message = ?, showssp = ?, showoperatorhome = ?, showlogin = ?
      WHERE id = ?
    " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "sssiiii",
      $form_values['newstype'],
      $form_values['title'],
      $form_values['message'],
      $form_values['showssp'],
      $form_values['showoperatorhome'],
      $form_values['showlogin'],
      $news_id
    );

    if ( mysqli_stmt_execute( $update_stmt ) ) {
      mysqli_stmt_close( $update_stmt );
      header( 'Location: edit_news.php?id=' . $news_id );
      exit;
    }

    $errors[] = 'Nieuwsbericht bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    mysqli_stmt_close( $update_stmt );
  }
}

$list_back_url = 'news.php';
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/back_links.php'); ?>
  <h1>Nieuwsbericht bewerken</h1>
  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <div class="form-card form-card-wide">
    <form method="post" class="form-grid">
      <div class="form-group">
        <label for="newstype">Type</label>
        <select id="newstype" name="newstype">
          <?php foreach ( $type_options as $type_key => $type_label ): ?>
          <option value="<?= htmlspecialchars($type_key) ?>" <?= $form_values['newstype'] === $type_key ? 'selected' : '' ?>><?= htmlspecialchars($type_label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($form_values['title']) ?>" required>
      </div>
      <div class="form-group">
        <label for="message">Bericht</label>
        <textarea id="message" name="message" rows="10" required><?= htmlspecialchars($form_values['message']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showssp" value="1" <?= (int)$form_values['showssp'] === 1 ? 'checked' : '' ?>> Toon op SelfService Portal</label>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showoperatorhome" value="1" <?= (int)$form_values['showoperatorhome'] === 1 ? 'checked' : '' ?>> Toon op Behandelaars Home</label>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showlogin" value="1" <?= (int)$form_values['showlogin'] === 1 ? 'checked' : '' ?>> Toon op inlogpagina</label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Nieuwsbericht opslaan</button>
      </div>
    </form>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
