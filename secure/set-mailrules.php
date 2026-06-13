<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/mail_helpers.php' );

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
$stmt = mysqli_prepare( $con, "SELECT id, isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, 's', $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $operator_id, $is_admin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( (int)$is_admin === 0 ) {
  header( 'Location: index.php' );
  exit;
}

$errors = [];
$message = '';
$edit_id = isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ? (int)$_GET['id'] : 0;

$task_types = [
  'incident' => t( 'mailrules.tasktype.incident' ),
  'change' => t( 'mailrules.tasktype.change' ),
  'problem' => t( 'mailrules.tasktype.problem' )
];
$trigger_types = [
  'statuschange' => t( 'mailrules.trigger.statuschange' ),
  'created' => t( 'mailrules.trigger.created' )
];
$recipient_types = [
  'requester' => t( 'mailrules.recipient.requester' ),
  'operator' => t( 'mailrules.recipient.operator' ),
  'coordinator' => t( 'mailrules.recipient.coordinator' ),
  'custom' => t( 'mailrules.recipient.custom' )
];
$variables = [
  'task_type',
  'task_id',
  'task_number',
  'task_url',
  'task_public_url',
  'title',
  'description',
  'latest_comment',
  'old_status_name',
  'status_name',
  'customer_name',
  'person_name',
  'person_email',
  'person_phone',
  'operator_name',
  'operator_email',
  'coordinator_name',
  'coordinator_email',
  'group_name',
  'createdat',
  'updatedat'
];

$form_values = [
  'id' => '',
  'tasktype' => 'incident',
  'triggertype' => 'statuschange',
  'fromstatusid' => '',
  'tostatusid' => '',
  'recipienttype' => 'requester',
  'customrecipients' => '',
  'subject' => '',
  'templatefile' => '',
  'active' => 1
];

if ( isset( $_POST['delete_rule'] ) && isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ) {
  $delete_id = (int)$_POST['id'];
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_mailrules WHERE id = ?" );
  mysqli_stmt_bind_param( $delete_stmt, 'i', $delete_id );
  mysqli_stmt_execute( $delete_stmt );
  header( 'Location: set-mailrules.php' );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['delete_rule'] ) ) {
  $form_values = [
    'id' => $_POST['id'] ?? '',
    'tasktype' => $_POST['tasktype'] ?? 'incident',
    'triggertype' => $_POST['triggertype'] ?? 'statuschange',
    'fromstatusid' => $_POST['fromstatusid'] ?? '',
    'tostatusid' => $_POST['tostatusid'] ?? '',
    'recipienttype' => $_POST['recipienttype'] ?? 'requester',
    'customrecipients' => trim( $_POST['customrecipients'] ?? '' ),
    'subject' => trim( $_POST['subject'] ?? '' ),
    'templatefile' => trim( $_POST['templatefile'] ?? '' ),
    'active' => isset( $_POST['active'] ) ? 1 : 0
  ];

  if ( !array_key_exists( $form_values['tasktype'], $task_types ) ) {
    $errors[] = t( 'mailrules.error.invalid_tasktype' );
  }
  if ( !array_key_exists( $form_values['triggertype'], $trigger_types ) ) {
    $errors[] = t( 'mailrules.error.invalid_trigger' );
  }
  if ( $form_values['triggertype'] === 'statuschange' ) {
    if ( $form_values['tostatusid'] === '' || !is_numeric( $form_values['tostatusid'] ) ) {
      $errors[] = t( 'mailrules.error.invalid_to_status' );
    }
    if ( $form_values['fromstatusid'] !== '' && !is_numeric( $form_values['fromstatusid'] ) ) {
      $errors[] = t( 'mailrules.error.invalid_from_status' );
    }
  }
  if ( !array_key_exists( $form_values['recipienttype'], $recipient_types ) ) {
    $errors[] = t( 'mailrules.error.invalid_recipient' );
  }
  if ( $form_values['subject'] === '' ) {
    $errors[] = t( 'mailrules.error.subject_required' );
  }
  if ( mail_template_safe_path( $form_values['templatefile'] ) === '' ) {
    $errors[] = t( 'mailrules.error.invalid_template' );
  }

  if ( empty( $errors ) ) {
    $rule_id = $form_values['id'] !== '' && is_numeric( $form_values['id'] ) ? (int)$form_values['id'] : 0;
    $tasktype = $form_values['tasktype'];
    $triggertype = $form_values['triggertype'];
    $fromstatusid = $triggertype === 'statuschange' && $form_values['fromstatusid'] !== '' ? (int)$form_values['fromstatusid'] : null;
    $tostatusid = $triggertype === 'statuschange' && $form_values['tostatusid'] !== '' ? (int)$form_values['tostatusid'] : null;
    $recipienttype = $form_values['recipienttype'];
    $customrecipients = $form_values['customrecipients'];
    $subject = $form_values['subject'];
    $templatefile = basename( $form_values['templatefile'] );
    $active = (int)$form_values['active'];
    $createdby = (int)$operator_id;

    if ( $rule_id > 0 ) {
      $save_stmt = mysqli_prepare( $con, "
        UPDATE itsm_core_mailrules
        SET tasktype = ?, triggertype = ?, fromstatusid = ?, tostatusid = ?, recipienttype = ?, customrecipients = ?, subject = ?, templatefile = ?, active = ?
        WHERE id = ?
      " );
      mysqli_stmt_bind_param( $save_stmt, 'ssiissssii', $tasktype, $triggertype, $fromstatusid, $tostatusid, $recipienttype, $customrecipients, $subject, $templatefile, $active, $rule_id );
    } else {
      $save_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_mailrules
          (tasktype, triggertype, fromstatusid, tostatusid, recipienttype, customrecipients, subject, templatefile, active, createdby)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      " );
      mysqli_stmt_bind_param( $save_stmt, 'ssiissssii', $tasktype, $triggertype, $fromstatusid, $tostatusid, $recipienttype, $customrecipients, $subject, $templatefile, $active, $createdby );
    }

    if ( !mysqli_stmt_execute( $save_stmt ) ) {
      $errors[] = t( 'mailrules.error.save_failed', [ 'error' => mysqli_stmt_error( $save_stmt ) ] );
    } else {
      header( 'Location: set-mailrules.php' );
      exit;
    }
  }
} elseif ( $edit_id > 0 ) {
  $edit_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_core_mailrules WHERE id = ?" );
  mysqli_stmt_bind_param( $edit_stmt, 'i', $edit_id );
  mysqli_stmt_execute( $edit_stmt );
  $edit_result = mysqli_stmt_get_result( $edit_stmt );
  $rule = mysqli_fetch_assoc( $edit_result );
  mysqli_stmt_close( $edit_stmt );

  if ( $rule ) {
    $form_values = [
      'id' => (string)$rule['id'],
      'tasktype' => $rule['tasktype'],
      'triggertype' => $rule['triggertype'] ?? 'statuschange',
      'fromstatusid' => (string)($rule['fromstatusid'] ?? ''),
      'tostatusid' => (string)($rule['tostatusid'] ?? ''),
      'recipienttype' => $rule['recipienttype'],
      'customrecipients' => $rule['customrecipients'] ?? '',
      'subject' => $rule['subject'],
      'templatefile' => $rule['templatefile'],
      'active' => (int)$rule['active']
    ];
  }
}

$statuses = mysqli_query( $con, "SELECT id, type, name FROM itsm_core_status ORDER BY type ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
$rules = mysqli_query( $con, "
  SELECT r.*, fs.name AS from_status_name, ts.name AS to_status_name
  FROM itsm_core_mailrules r
  LEFT JOIN itsm_core_status fs ON r.fromstatusid = fs.id
  LEFT JOIN itsm_core_status ts ON r.tostatusid = ts.id
  ORDER BY r.tasktype ASC, r.triggertype ASC, r.id ASC
" );
$template_files = [];
$template_dir = mail_template_dir();
if ( is_dir( $template_dir ) ) {
  foreach ( glob( $template_dir . '/*.html' ) ?: [] as $file ) {
    $template_files[] = basename( $file );
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'settings.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center><h1><?= htmlspecialchars( t( 'mailrules.title' ) ) ?></h1></center>

  <?php foreach ( $errors as $error ): ?>
    <p class="ssp-error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <h2><?= htmlspecialchars( $form_values['id'] !== '' ? t( 'mailrules.edit' ) : t( 'mailrules.new' ) ) ?></h2>
      <p class="info-note"><?= t( 'mailrules.config_help', [
        'smtp_path' => '<code>../config/smtp.ini</code>',
        'template_path' => '<code>secure/itsm-config/templates/mail</code>'
      ] ) ?></p>
      <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$form_values['id']) ?>">

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'Taaksoort' ) ) ?></label>
          <select name="tasktype" required>
            <?php foreach ( $task_types as $value => $label ): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $form_values['tasktype'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'Trigger' ) ) ?></label>
          <select name="triggertype" id="mailrule_trigger_type" required>
            <?php foreach ( $trigger_types as $value => $label ): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $form_values['triggertype'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group mailrule-status-field">
          <label><?= htmlspecialchars( t( 'Van status' ) ) ?></label>
          <select name="fromstatusid">
            <option value=""><?= htmlspecialchars( t( 'Elke vorige status' ) ) ?></option>
            <?php foreach ( $statuses as $status ): ?>
              <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['fromstatusid'] === (string)$status['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($status['type'] . ' - ' . $status['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group mailrule-status-field">
          <label><?= htmlspecialchars( t( 'Naar status' ) ) ?></label>
          <select name="tostatusid" id="mailrule_to_status">
            <option value=""><?= htmlspecialchars( t( 'Selecteer status' ) ) ?></option>
            <?php foreach ( $statuses as $status ): ?>
              <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['tostatusid'] === (string)$status['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($status['type'] . ' - ' . $status['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'Ontvanger' ) ) ?></label>
          <select name="recipienttype" required>
            <?php foreach ( $recipient_types as $value => $label ): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $form_values['recipienttype'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'Aangepaste ontvangers' ) ) ?></label>
          <textarea name="customrecipients" placeholder="<?= htmlspecialchars( t( 'mailrules.custom_placeholder' ) ) ?>"><?= htmlspecialchars($form_values['customrecipients']) ?></textarea>
        </div>

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'Onderwerp' ) ) ?></label>
          <input type="text" name="subject" value="<?= htmlspecialchars($form_values['subject']) ?>" placeholder="<?= htmlspecialchars( t( 'mailrules.subject_placeholder' ) ) ?>" required>
        </div>

        <div class="form-group">
          <label><?= htmlspecialchars( t( 'HTML-template' ) ) ?></label>
          <input type="text" name="templatefile" list="mail_template_files" value="<?= htmlspecialchars($form_values['templatefile']) ?>" placeholder="<?= htmlspecialchars( t( 'mailrules.template_placeholder' ) ) ?>" required>
          <datalist id="mail_template_files">
            <?php foreach ( $template_files as $file ): ?>
              <option value="<?= htmlspecialchars($file) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <label class="checkbox-label"><input type="checkbox" name="active" <?= !empty($form_values['active']) ? 'checked' : '' ?>> <?= htmlspecialchars( t( 'Actief' ) ) ?></label>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= htmlspecialchars( t( 'mailrules.save' ) ) ?></button>
          <?php if ( $form_values['id'] !== '' ): ?>
            <button type="submit" name="delete_rule" class="btn-danger" onclick="return confirm('<?= htmlspecialchars( t( 'mailrules.delete_confirm' ), ENT_QUOTES ) ?>');"><?= htmlspecialchars( t( 'mailrules.delete' ) ) ?></button>
            <a href="set-mailrules.php"><?= htmlspecialchars( t( 'mailrules.new_link' ) ) ?></a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="form-card form-card-wide record-form-card">
    <h2><?= htmlspecialchars( t( 'Beschikbare variabelen' ) ) ?></h2>
    <p class="info-note"><?= t( 'mailrules.variables_help' ) ?></p>
    <p><?= htmlspecialchars( implode( ', ', $variables ) ) ?></p>
  </div>

  <div class="results">
    <table>
      <thead>
        <tr>
          <th><?= htmlspecialchars( t( 'Taaksoort' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'Trigger' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'mailrules.from' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'mailrules.to' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'Ontvanger' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'mailrules.template' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'Actief' ) ) ?></th>
          <th><?= htmlspecialchars( t( 'Actie' ) ) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $rule = mysqli_fetch_assoc( $rules ) ): ?>
          <tr>
            <td><?= htmlspecialchars($task_types[$rule['tasktype']] ?? $rule['tasktype']) ?></td>
            <td><?= htmlspecialchars($trigger_types[$rule['triggertype'] ?? 'statuschange'] ?? ($rule['triggertype'] ?? '')) ?></td>
            <td><?= ($rule['triggertype'] ?? 'statuschange') === 'created' ? '-' : htmlspecialchars($rule['from_status_name'] ?? t( 'Elke vorige status' )) ?></td>
            <td><?= ($rule['triggertype'] ?? 'statuschange') === 'created' ? '-' : htmlspecialchars($rule['to_status_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($recipient_types[$rule['recipienttype']] ?? $rule['recipienttype']) ?></td>
            <td><?= htmlspecialchars($rule['templatefile']) ?></td>
            <td><?= (int)$rule['active'] === 1 ? htmlspecialchars( t( 'Ja' ) ) : htmlspecialchars( t( 'Nee' ) ) ?></td>
            <td class="tblaction"><a href="set-mailrules.php?id=<?= htmlspecialchars((string)$rule['id']) ?>"><?= htmlspecialchars( t( 'mailrules.open_rule' ) ) ?></a></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function () {
  const trigger = document.getElementById('mailrule_trigger_type');
  const toStatus = document.getElementById('mailrule_to_status');
  const statusFields = document.querySelectorAll('.mailrule-status-field');

  function refreshMailRuleStatusFields() {
    const needsStatus = !trigger || trigger.value === 'statuschange';
    statusFields.forEach((field) => {
      field.style.display = needsStatus ? '' : 'none';
    });
    if (toStatus) {
      toStatus.required = needsStatus;
    }
  }

  if (trigger) {
    trigger.addEventListener('change', refreshMailRuleStatusFields);
  }
  refreshMailRuleStatusFields();
})();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
