<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/kb_helpers.php' );
require_once( __DIR__ . '/include/markdown_helpers.php' );

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

$all_items = kb_load_all_items( $con );
$tree = kb_build_tree( $all_items );
$default_parent_id = isset( $_GET['parentid'] ) && is_numeric( $_GET['parentid'] ) ? (int)$_GET['parentid'] : 0;
$errors = [];
$creator_id = (int)$_SESSION['id'];

$form_values = [
  'title' => '',
  'content' => '',
  'parentid' => $default_parent_id > 0 ? (string)$default_parent_id : '',
  'publicaccess' => 1
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'title' => trim( $_POST['title'] ?? '' ),
    'content' => trim( $_POST['content'] ?? '' ),
    'parentid' => $_POST['parentid'] ?? '',
    'publicaccess' => isset( $_POST['publicaccess'] ) ? 1 : 0
  ];

  $parent_id = $form_values['parentid'] !== '' ? (int)$form_values['parentid'] : null;
  if ( $form_values['title'] === '' ) {
    $errors[] = 'Titel is verplicht.';
  }
  if ( $form_values['content'] === '' ) {
    $errors[] = 'Inhoud is verplicht.';
  }
  if ( $parent_id !== null && !kb_find_item_by_id( $all_items, $parent_id ) ) {
    $errors[] = 'Geselecteerd bovenliggend kennisitem bestaat niet.';
  }

  if ( empty( $errors ) ) {
    $stmt = mysqli_prepare( $con, "
      INSERT INTO itsm_km_items (parentid, title, content, publicaccess, createdby)
      VALUES (?,?,?,?,?)
    " );
    mysqli_stmt_bind_param(
      $stmt,
      "issii",
      $parent_id,
      $form_values['title'],
      $form_values['content'],
      $form_values['publicaccess'],
      $creator_id
    );

    if ( mysqli_stmt_execute( $stmt ) ) {
      $item_id = mysqli_insert_id( $con );
      mysqli_stmt_close( $stmt );
      header( 'Location: edit_kb_item.php?id=' . $item_id );
      exit;
    }

    $errors[] = 'Kennisitem opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
}

$list_back_url = 'kb_items.php';
$module_back_url = 'kb-menu.php';
$parent_options = kb_flatten_tree_options( $tree );
$markdown_preview = markdown_to_html( $form_values['content'] );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/back_links.php'); ?>
  <h1>Nieuw kennisitem</h1>

  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="form-card form-card-wide">
    <form method="post" class="form-grid">
      <div class="form-group">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($form_values['title']) ?>" required>
      </div>
      <div class="form-group">
        <label for="parentid">Bovenliggend kennisitem</label>
        <select id="parentid" name="parentid">
          <option value="">Geen, dit is een hoofditem</option>
          <?php foreach ( $parent_options as $option ): ?>
          <option value="<?= (int)$option['id'] ?>" <?= (int)$form_values['parentid'] === (int)$option['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($option['label']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="checkbox-label"><input type="checkbox" name="publicaccess" value="1" <?= (int)$form_values['publicaccess'] === 1 ? 'checked' : '' ?>> Zichtbaar in Self Service Portal</label>
      </div>
      <div class="form-group">
        <label for="content">Inhoud (Markdown)</label>
        <textarea id="content" name="content" class="kb-editor" rows="24" required><?= htmlspecialchars($form_values['content']) ?></textarea>
        <p class="info-note">Ondersteunt Markdown</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Kennisitem opslaan</button>
      </div>
    </form>
  </div>

  <div class="form-card form-card-wide" style="margin-top: 20px;">
    <h2>Voorbeeld</h2>
    <div class="kb-markdown"><?= $markdown_preview ?></div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
