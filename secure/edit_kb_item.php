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
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
}

$item_id = (int)$_GET['id'];
$all_items = kb_load_all_items( $con );
$item = kb_find_item_by_id( $all_items, $item_id );
if ( !$item ) {
  die( 'Kennisitem niet gevonden' );
}

$errors = [];
$tree = kb_build_tree( $all_items );
$child_items = array_values( array_filter( $all_items, function( $row ) use ( $item_id ) {
  return isset( $row['parentid'] ) && (int)$row['parentid'] === $item_id;
} ) );

$form_values = [
  'title' => $item['title'],
  'content' => $item['content'],
  'parentid' => $item['parentid'] !== null ? (string)$item['parentid'] : '',
  'publicaccess' => (int)$item['publicaccess']
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  if ( isset( $_POST['delete_item'] ) ) {
    if ( !empty( $child_items ) ) {
      $errors[] = 'Verwijderen is niet mogelijk zolang dit kennisitem nog subitems heeft.';
    } else {
      $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_km_items WHERE id = ?" );
      mysqli_stmt_bind_param( $delete_stmt, "i", $item_id );

      if ( mysqli_stmt_execute( $delete_stmt ) ) {
        mysqli_stmt_close( $delete_stmt );
        $redirect_url = $item['parentid'] !== null
          ? 'view_kb_item.php?id=' . (int)$item['parentid']
          : 'kb_items.php';
        header( 'Location: ' . $redirect_url );
        exit;
      }

      $errors[] = 'Kennisitem verwijderen mislukt: ' . mysqli_stmt_error( $delete_stmt );
      mysqli_stmt_close( $delete_stmt );
    }
  }

  if ( empty( $errors ) && !isset( $_POST['delete_item'] ) ) {
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
  if ( $parent_id !== null && $parent_id === $item_id ) {
    $errors[] = 'Een kennisitem kan niet onder zichzelf hangen.';
  }
  if ( $parent_id !== null && kb_is_descendant( $all_items, $parent_id, $item_id ) ) {
    $errors[] = 'Je kan een kennisitem niet onder een eigen subitem hangen.';
  }

  if ( empty( $errors ) ) {
    $stmt = mysqli_prepare( $con, "
      UPDATE itsm_km_items
      SET parentid = ?, title = ?, content = ?, publicaccess = ?
      WHERE id = ?
    " );
    mysqli_stmt_bind_param(
      $stmt,
      "issii",
      $parent_id,
      $form_values['title'],
      $form_values['content'],
      $form_values['publicaccess'],
      $item_id
    );

    if ( mysqli_stmt_execute( $stmt ) ) {
      mysqli_stmt_close( $stmt );
      header( 'Location: edit_kb_item.php?id=' . $item_id );
      exit;
    }

    $errors[] = 'Kennisitem bijwerken mislukt: ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
  }
}

$list_back_url = 'kb_items.php';
$module_back_url = 'kb-menu.php';
$parent_options = kb_flatten_tree_options( $tree, 0, $item_id );
$markdown_preview = markdown_to_html( $form_values['content'] );
$breadcrumbs = kb_build_breadcrumbs( $all_items, $item_id );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/back_links.php'); ?>
  <h1>Kennisitem bewerken</h1>

  <?php if ( !empty( $breadcrumbs ) ): ?>
  <p class="info-note">
    Pad:
    <?php foreach ( $breadcrumbs as $index => $crumb ): ?>
    <?php if ( $index > 0 ): ?> / <?php endif; ?>
    <a class="task-inline-link" href="view_kb_item.php?id=<?= (int)$crumb['id'] ?>"><?= htmlspecialchars($crumb['title']) ?></a>
    <?php endforeach; ?>
  </p>
  <?php endif; ?>

  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="inline-link-row" style="margin-bottom: 14px;">
    <a href="new_kb_item.php?parentid=<?= $item_id ?>">Nieuw subitem</a>
    <?php if ( (int)$item['publicaccess'] === 1 ): ?>
    <a href="../public/view_kb_item.php?id=<?= $item_id ?>">Open publieke weergave</a>
    <?php endif; ?>
  </div>

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
        <p class="info-note">Ondersteunt Discord-achtige Markdown zoals `# koppen`, `**vet**`, `*cursief*`, `__onderstreept__`, `~~doorgestreept~~`, `> quotes`, lijstjes, links en codeblokken.</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Kennisitem opslaan</button>
        <button type="submit" name="delete_item" value="1" class="btn-danger" formnovalidate onclick="return confirm('Weet je zeker dat je dit kennisitem wilt verwijderen?');">Kennisitem verwijderen</button>
      </div>
    </form>
  </div>

  <div class="form-card form-card-wide" style="margin-top: 20px;">
    <h2>Voorbeeld</h2>
    <div class="kb-markdown"><?= $markdown_preview ?></div>
  </div>

  <div class="form-card form-card-wide" style="margin-top: 20px;">
    <h2>Subitems</h2>
    <?php if ( empty( $child_items ) ): ?>
    <p>Dit kennisitem heeft nog geen subitems.</p>
    <?php else: ?>
    <ul class="kb-tree">
      <?php foreach ( $child_items as $child ): ?>
      <li>
        <a class="task-inline-link" href="view_kb_item.php?id=<?= (int)$child['id'] ?>"><?= htmlspecialchars($child['title']) ?></a>
        <span class="kb-visibility-badge<?= (int)$child['publicaccess'] === 1 ? ' is-public' : '' ?>">
          <?= (int)$child['publicaccess'] === 1 ? 'Publiek' : 'Intern' ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
