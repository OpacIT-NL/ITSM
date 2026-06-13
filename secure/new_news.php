<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/news_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION['name'];
news_require_firstline_authorization( $con, $logged_in_user, 'news-menu.php' );
$errors = [];
$creator_id = (int)$_SESSION['id'];
$type_options = news_type_options();
$form_values = [
  'newstype' => 'general',
  'title' => '',
  'message' => '',
  'showssp' => 1,
  'showoperatorhome' => 1,
  'showlogin' => 0
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
    $errors[] = t('Selecteer een geldig type nieuwsbericht.');
  }
  if ( $form_values['title'] === '' ) {
    $errors[] = t('Titel') . ' ' . t('is verplicht.');
  }
  if ( $form_values['message'] === '' ) {
    $errors[] = t('Bericht') . ' ' . t('is verplicht.');
  }

  if ( empty( $errors ) ) {
    $stmt = mysqli_prepare( $con, "
      INSERT INTO itsm_core_news (newstype, title, message, showssp, showoperatorhome, showlogin, createdby)
      VALUES (?,?,?,?,?,?,?)
    " );
    mysqli_stmt_bind_param(
      $stmt,
      "sssiiii",
      $form_values['newstype'],
      $form_values['title'],
      $form_values['message'],
      $form_values['showssp'],
      $form_values['showoperatorhome'],
      $form_values['showlogin'],
      $creator_id
    );

    if ( mysqli_stmt_execute( $stmt ) ) {
      $news_id = mysqli_insert_id( $con );
      mysqli_stmt_close( $stmt );
      header( 'Location: edit_news.php?id=' . $news_id );
      exit;
    }

    $errors[] = t('Nieuwsbericht opslaan mislukt:') . ' ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
}

$list_back_url = 'news.php';
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/back_links.php'); ?>
  <h1><?= htmlspecialchars(t('Nieuw nieuwsbericht')) ?></h1>
  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <div class="form-card form-card-wide">
    <form method="post" class="form-grid">
      <div class="form-group">
        <label for="newstype"><?= htmlspecialchars(t('Type')) ?></label>
        <select id="newstype" name="newstype">
          <?php foreach ( $type_options as $type_key => $type_label ): ?>
          <option value="<?= htmlspecialchars($type_key) ?>" <?= $form_values['newstype'] === $type_key ? 'selected' : '' ?>><?= htmlspecialchars($type_label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="title"><?= htmlspecialchars(t('Titel')) ?></label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($form_values['title']) ?>" required>
      </div>
      <div class="form-group">
        <label for="message"><?= htmlspecialchars(t('Bericht')) ?></label>
        <textarea id="message" name="message" rows="10" required><?= htmlspecialchars($form_values['message']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showssp" value="1" <?= (int)$form_values['showssp'] === 1 ? 'checked' : '' ?>> <?= htmlspecialchars(t('Toon op SelfService Portal')) ?></label>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showoperatorhome" value="1" <?= (int)$form_values['showoperatorhome'] === 1 ? 'checked' : '' ?>> <?= htmlspecialchars(t('Toon op Behandelaars Home')) ?></label>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="showlogin" value="1" <?= (int)$form_values['showlogin'] === 1 ? 'checked' : '' ?>> <?= htmlspecialchars(t('Toon op inlogpagina')) ?></label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(t('Nieuwsbericht opslaan')) ?></button>
      </div>
    </form>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
