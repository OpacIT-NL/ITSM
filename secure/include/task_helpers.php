<?php

function task_supported_types() {
  return [
    'incident' => [ 'prefix' => 'I', 'number_field' => 'incidentnumber', 'table' => 'itsm_im_incidents', 'title_field' => 'title', 'route' => 'edit_incident.php' ],
    'change' => [ 'prefix' => 'W', 'number_field' => 'changenumber', 'table' => 'itsm_cm_changes', 'title_field' => 'title', 'route' => 'edit_change.php' ],
    'changeactivity' => [ 'prefix' => 'WA', 'number_field' => 'activitynumber', 'table' => 'itsm_cm_changeactivities', 'title_field' => 'title', 'route' => 'edit_change_activity.php' ],
    'problem' => [ 'prefix' => 'P', 'number_field' => 'problemnumber', 'table' => 'itsm_pm_problems', 'title_field' => 'title', 'route' => 'edit_problem.php' ],
    'event' => [ 'prefix' => 'E', 'number_field' => 'eventnumber', 'table' => 'itsm_em_events', 'title_field' => 'description', 'route' => 'edit_event.php' ]
  ];
}

function task_normalize_number( $value ) {
  $value = strtoupper( trim( (string)$value ) );
  $value = preg_replace( '/\s+/', ' ', $value );
  return $value;
}

function task_detect_type_from_number( $number ) {
  $number = task_normalize_number( $number );
  if ( preg_match( '/^WA\d{4}\s\d{4}$/', $number ) ) {
    return 'changeactivity';
  }
  if ( preg_match( '/^W\d{4}\s\d{4}$/', $number ) ) {
    return 'change';
  }
  if ( preg_match( '/^I\d{4}\s\d{4}$/', $number ) ) {
    return 'incident';
  }
  if ( preg_match( '/^P\d{4}\s\d{4}$/', $number ) ) {
    return 'problem';
  }
  if ( preg_match( '/^E\d{4}\s\d{4}$/', $number ) ) {
    return 'event';
  }

  return null;
}

function task_build_url( $type, $id, $context = 'secure' ) {
  $types = task_supported_types();
  if ( !isset( $types[$type] ) ) {
    return '';
  }

  if ( $context === 'public' ) {
    if ( $type === 'incident' ) {
      return 'view_incident.php?id=' . (int)$id;
    }
    if ( $type === 'change' ) {
      return 'view_change.php?id=' . (int)$id;
    }
    return '';
  }

  return $types[$type]['route'] . '?id=' . (int)$id;
}

function task_find_by_number( $con, $number, $context = 'secure' ) {
  $number = task_normalize_number( $number );
  $type = task_detect_type_from_number( $number );
  $types = task_supported_types();
  if ( !$type || !isset( $types[$type] ) ) {
    return null;
  }

  $config = $types[$type];
  $stmt = mysqli_prepare( $con, "SELECT id, {$config['number_field']} AS tasknumber, {$config['title_field']} AS tasktitle FROM {$config['table']} WHERE {$config['number_field']} = ? LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, "s", $number );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$row ) {
    return null;
  }

  return [
    'type' => $type,
    'id' => (int)$row['id'],
    'number' => $row['tasknumber'],
    'title' => $row['tasktitle'] ?? '',
    'url' => task_build_url( $type, (int)$row['id'], $context )
  ];
}

function task_linkify_text( $text, $context = 'secure' ) {
  $escaped = htmlspecialchars( (string)$text );
  $pattern = '/\b(WA\d{4}\s\d{4}|W\d{4}\s\d{4}|I\d{4}\s\d{4}|P\d{4}\s\d{4}|E\d{4}\s\d{4})\b/';

  $linked = preg_replace_callback( $pattern, function ( $matches ) use ( $context ) {
    $number = task_normalize_number( $matches[1] );
    $type = task_detect_type_from_number( $number );
    if ( !$type ) {
      return $matches[1];
    }
    if ( $context === 'secure' ) {
      return '<a class="task-inline-link" href="search.php?tasknumber=' . rawurlencode( $number ) . '">' . htmlspecialchars( $number ) . '</a>';
    }
    if ( $type === 'incident' ) {
      return '<a class="task-inline-link" href="view_incident.php?tasknumber=' . rawurlencode( $number ) . '">' . htmlspecialchars( $number ) . '</a>';
    }
    if ( $type === 'change' ) {
      return '<a class="task-inline-link" href="view_change.php?tasknumber=' . rawurlencode( $number ) . '">' . htmlspecialchars( $number ) . '</a>';
    }
    return htmlspecialchars( $number );
  }, $escaped );

  return nl2br( $linked );
}

function task_relation_options() {
  return [
    'Gerelateerd aan',
    'Veroorzaakt door',
    'Oplossing voor',
    'Afhankelijk van',
    'Afgeleid van',
    'Behoort bij problem'
  ];
}

function task_relation_reverse_label( $relation ) {
  $map = [
    'Gerelateerd aan' => 'Gerelateerd aan',
    'Veroorzaakt door' => 'Oorzaak van',
    'Oplossing voor' => 'Wordt opgelost door',
    'Afhankelijk van' => 'Voorwaarde voor',
    'Afgeleid van' => 'Leidt tot',
    'Behoort bij problem' => 'Heeft gekoppeld incident'
  ];

  return $map[$relation] ?? $relation;
}

function task_create_link( $con, $left_type, $left_id, $relation, $right_type, $right_id, $created_by ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id
        FROM itsm_core_tasklinks
        WHERE lefttype = ? AND leftid = ? AND relationtype = ? AND righttype = ? AND rightid = ?
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "sissi", $left_type, $left_id, $relation, $right_type, $right_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $existing = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( $existing ) {
    return (int)$existing['id'];
  }

  $insert_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_tasklinks (lefttype, leftid, relationtype, righttype, rightid, createdby)
        VALUES (?,?,?,?,?,?)
    " );
  mysqli_stmt_bind_param( $insert_stmt, "sissii", $left_type, $left_id, $relation, $right_type, $right_id, $created_by );
  mysqli_stmt_execute( $insert_stmt );
  $id = mysqli_insert_id( $con );
  mysqli_stmt_close( $insert_stmt );

  return $id;
}

function task_delete_link( $con, $link_id ) {
  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_tasklinks WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $link_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
}

function task_get_display_by_type_id( $con, $type, $id, $context = 'secure' ) {
  $types = task_supported_types();
  if ( !isset( $types[$type] ) ) {
    return null;
  }

  $config = $types[$type];
  $stmt = mysqli_prepare( $con, "SELECT id, {$config['number_field']} AS tasknumber, {$config['title_field']} AS tasktitle FROM {$config['table']} WHERE id = ? LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, "i", $id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$row ) {
    return null;
  }

  return [
    'type' => $type,
    'id' => (int)$row['id'],
    'number' => $row['tasknumber'],
    'title' => $row['tasktitle'] ?? '',
    'url' => task_build_url( $type, (int)$row['id'], $context )
  ];
}

function task_load_links( $con, $source_type, $source_id, $context = 'secure' ) {
  $stmt = mysqli_prepare( $con, "
        SELECT *
        FROM itsm_core_tasklinks
        WHERE (lefttype = ? AND leftid = ?) OR (righttype = ? AND rightid = ?)
        ORDER BY id DESC
    " );
  mysqli_stmt_bind_param( $stmt, "sisi", $source_type, $source_id, $source_type, $source_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $links = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $source_is_left = $row['lefttype'] === $source_type && (int)$row['leftid'] === (int)$source_id;
    $other_type = $source_is_left ? $row['righttype'] : $row['lefttype'];
    $other_id = $source_is_left ? (int)$row['rightid'] : (int)$row['leftid'];
    $other = task_get_display_by_type_id( $con, $other_type, $other_id, $context );
    if ( !$other ) {
      continue;
    }
    $row['other'] = $other;
    $row['relation_label'] = $source_is_left ? $row['relationtype'] : task_relation_reverse_label( $row['relationtype'] );
    $links[] = $row;
  }
  mysqli_stmt_close( $stmt );

  return $links;
}

function task_render_links_section( $links, $delete_button_name = 'delete_link_id' ) {
  ob_start();
  ?>
  <hr>
  <h3>Links</h3>
  <div class="incident-history">
    <?php if ( empty( $links ) ): ?>
    <p>Nog geen links.</p>
    <?php else: ?>
    <?php foreach ( $links as $link ): ?>
    <div class="incident-comment">
      <div class="incident-comment-meta">
        <span><?= htmlspecialchars($link['relation_label']) ?></span>
        <span><?= htmlspecialchars($link['other']['number']) ?></span>
      </div>
      <p><a class="task-inline-link" href="<?= htmlspecialchars($link['other']['url']) ?>"><?= htmlspecialchars($link['other']['number']) ?></a> <?= htmlspecialchars($link['other']['title']) ?></p>
      <div class="form-actions">
        <button type="submit" name="<?= htmlspecialchars($delete_button_name) ?>" value="<?= htmlspecialchars((string)$link['id']) ?>" class="btn-danger" formnovalidate>Link verwijderen</button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="form-actions">
    <select name="link_relationtype">
      <option value="">Selecteer linktype</option>
      <?php foreach ( task_relation_options() as $relation_option ): ?>
      <option value="<?= htmlspecialchars($relation_option) ?>"><?= htmlspecialchars($relation_option) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="link_tasknumber" placeholder="Taaknummer, bijvoorbeeld W2604 0001">
    <button type="submit" name="add_task_link" value="1" class="btn-primary" formnovalidate>Link toevoegen</button>
  </div>
  <?php
  return ob_get_clean();
}

function task_prefill_from_source( $con, $source_type, $source_id ) {
  if ( $source_type === 'incident' ) {
    $stmt = mysqli_prepare( $con, "
            SELECT id, title, description, customerid, personid, personemail, personphone, categoryid, subcategoryid, assetid
            FROM itsm_im_incidents
            WHERE id = ?
            LIMIT 1
        " );
  } elseif ( $source_type === 'problem' ) {
    $stmt = mysqli_prepare( $con, "
            SELECT id, title, description, customerid, personid, personemail, personphone, categoryid, subcategoryid, assetid
            FROM itsm_pm_problems
            WHERE id = ?
            LIMIT 1
        " );
  } else {
    return null;
  }

  mysqli_stmt_bind_param( $stmt, "i", $source_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  return $row ?: null;
}
