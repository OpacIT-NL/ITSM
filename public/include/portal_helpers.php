<?php
require_once( __DIR__ . '/../../include/session_helpers.php' );
require_once( __DIR__ . '/../../include/upload_security.php' );

function ssp_require_login( $con ) {
  itsm_secure_session_start();

  if ( !isset( $_SESSION['ssploggedin'], $_SESSION['id'] ) ) {
    header( 'Location: login.php' );
    exit;
  }

  if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
    itsm_destroy_session();
    header( 'Location: login.php?expired=1' );
    exit;
  }

  $person_id = (int)$_SESSION['id'];
  $stmt = mysqli_prepare( $con, "
        SELECT p.id, p.customerid, p.firstname, p.lastname, p.email, p.phone, p.allowssp, p.preferredlanguage, c.name AS customer_name, c.defaultlanguage
        FROM itsm_ob_persons p
        LEFT JOIN itsm_ob_customers c ON p.customerid = c.id
        WHERE p.id = ?
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "i", $person_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $person = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$person || (int)$person['allowssp'] !== 1 ) {
    itsm_destroy_session();
    header( 'Location: login.php' );
    exit;
  }

  $_SESSION['expires_at'] = time() + ( 12 * 60 * 60 );
  $_SESSION['name'] = trim( ($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '') );
  $_SESSION['email'] = $person['email'] ?? '';
  if ( !empty( $person['preferredlanguage'] ) ) {
    $_SESSION['preferred_language'] = $person['preferredlanguage'];
  } elseif ( !empty( $person['defaultlanguage'] ) ) {
    $_SESSION['preferred_language'] = $person['defaultlanguage'];
  } else {
    unset( $_SESSION['preferred_language'] );
  }
  if ( function_exists( 'itsm_refresh_language_system' ) ) {
    itsm_refresh_language_system( $con );
  }

  $group_stmt = mysqli_prepare( $con, "
        SELECT l.persongroup, g.groupname
        FROM itsm_ob_persongrouplinks l
        LEFT JOIN itsm_ob_persongroups g ON l.persongroup = g.id
        WHERE l.person = ?
        ORDER BY g.groupname ASC
    " );
  mysqli_stmt_bind_param( $group_stmt, "i", $person_id );
  mysqli_stmt_execute( $group_stmt );
  $group_result = mysqli_stmt_get_result( $group_stmt );
  $person_groups = mysqli_fetch_all( $group_result, MYSQLI_ASSOC );
  mysqli_stmt_close( $group_stmt );
  $person['persongroups'] = $person_groups;

  return $person;
}

function ssp_page_title( $title ) {
  $safe_title = htmlspecialchars( $title );
  echo "<!doctype html>\n";
  echo "<html>\n";
  echo "<head>\n";
  echo "<meta charset=\"utf-8\">\n";
  echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
  echo "<link rel=\"stylesheet\" href=\"include/style.css\">\n";
  echo "<link rel=\"stylesheet\" href=\"https://use.fontawesome.com/releases/v7.2.0/css/all.css\">\n";
  echo "<title>{$safe_title}</title>\n";
  echo "</head>\n";
  echo "<body class=\"ssp-body\">\n";
}

function ssp_render_header( $person, $active = 'dashboard' ) {
  $name = htmlspecialchars( trim( ($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '') ) );
  $customer = htmlspecialchars( $person['customer_name'] ?? '' );
  $links = [
    'dashboard' => [ 'href' => 'index.php', 'label' => 'Overzicht', 'icon' => 'fa-house' ],
    'incidents' => [ 'href' => 'incidents.php', 'label' => 'Incidenten', 'icon' => 'fa-triangle-exclamation' ],
    'changes' => [ 'href' => 'changes.php', 'label' => 'Wijzigingen', 'icon' => 'fa-pen-to-square' ],
    'knowledge' => [ 'href' => 'knowledge.php', 'label' => 'Kennisbank', 'icon' => 'fa-book-open' ],
    'assets' => [ 'href' => 'assets.php', 'label' => 'Assets', 'icon' => 'fa-laptop' ],
    'new_incident' => [ 'href' => 'new_incident.php', 'label' => 'Incident melden', 'icon' => 'fa-phone' ],
    'new_change' => [ 'href' => 'new_change.php', 'label' => 'Wijziging aanvragen', 'icon' => 'fa-file-circle-plus' ],
    'profile' => [ 'href' => 'profile.php', 'label' => 'Profiel', 'icon' => 'fa-user' ]
  ];
  if ( ssp_person_has_group_name( $person, 'SSP_InfraShop' ) ) {
    $links['infra_shop'] = [ 'href' => 'infra_shop.php', 'label' => 'InfraShop', 'icon' => 'fa-cart-shopping' ];
  }

  echo "<div class=\"ssp-shell\">\n";
  echo "  <aside class=\"ssp-sidebar\">\n";
  echo "    <div class=\"ssp-brand\">\n";
  echo "      <div class=\"ssp-brand-mark\">IT</div>\n";
  echo "      <div>\n";
  echo "        <strong>Selfservice</strong>\n";
  echo "        <span>{$customer}</span>\n";
  echo "      </div>\n";
  echo "    </div>\n";
  echo "    <nav class=\"ssp-nav\">\n";
  foreach ( $links as $key => $link ) {
    $active_class = $active === $key ? ' is-active' : '';
    echo '      <a class="ssp-nav-link' . $active_class . '" href="' . htmlspecialchars( $link['href'] ) . '">';
    echo '<i class="fa-solid ' . htmlspecialchars( $link['icon'] ) . '"></i><span>' . htmlspecialchars( $link['label'] ) . "</span></a>\n";
  }
  echo "    </nav>\n";
  echo "  </aside>\n";
  echo "  <main class=\"ssp-main\">\n";
  echo "    <header class=\"ssp-topbar\">\n";
  echo "      <div>\n";
  echo "        <h1>ITSM Selfservice</h1>\n";
  echo '        <p>' . htmlspecialchars( t( 'ssp.logged_in_as', [ 'name' => trim( ($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '') ) ] ) ) . "</p>\n";
  echo "      </div>\n";
  echo "      <div class=\"ssp-topbar-actions\">\n";
  echo "        <form method=\"post\" action=\"logout.php\" class=\"ssp-logout-form\">";
  echo "<button class=\"ssp-ghost-link\" type=\"submit\">Uitloggen <i class=\"fa-solid fa-right-from-bracket\"></i></button></form>\n";
  echo "      </div>\n";
  echo "    </header>\n";
}

function ssp_render_footer() {
  echo "  </main>\n";
  echo "</div>\n";
  if ( function_exists( 'itsm_render_local_datetime_script' ) ) {
    itsm_render_local_datetime_script();
  }
  echo "</body>\n";
  echo "</html>\n";
}

function ssp_get_default_operator_id( $con ) {
  $result = mysqli_query( $con, "SELECT id FROM itsm_ob_operators ORDER BY isadmin DESC, id ASC LIMIT 1" );
  $row = $result ? mysqli_fetch_assoc( $result ) : null;

  return $row ? (int)$row['id'] : 0;
}

function ssp_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function ssp_incident_generate_number( $con ) {
  $prefix = 'I' . date( 'ym' );
  $like_prefix = $prefix . ' %';
  $stmt = mysqli_prepare( $con, "SELECT incidentnumber FROM itsm_im_incidents WHERE incidentnumber LIKE ? ORDER BY incidentnumber DESC LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['incidentnumber'] ) ) {
    $next_number = (int)substr( $row['incidentnumber'], -4 ) + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function ssp_change_generate_number( $con ) {
  $prefix = 'W' . date( 'ym' );
  $like_prefix = $prefix . ' %';
  $stmt = mysqli_prepare( $con, "SELECT changenumber FROM itsm_cm_changes WHERE changenumber LIKE ? ORDER BY changenumber DESC LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['changenumber'] ) ) {
    $next_number = (int)substr( $row['changenumber'], -4 ) + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function ssp_default_operator_group_id( $con, $default_name = 'Servicedesk' ) {
  $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_ob_operatorgroups WHERE groupname = ? LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, "s", $default_name );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  return $row ? (int)$row['id'] : 0;
}

function ssp_incident_reference_data( $con, $person ) {
  $categories_result = mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'INCIDENT' ORDER BY name ASC" );
  $categories = $categories_result ? mysqli_fetch_all( $categories_result, MYSQLI_ASSOC ) : [];
  $statuses_result = mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'INCIDENT' ORDER BY name ASC" );
  $statuses = $statuses_result ? mysqli_fetch_all( $statuses_result, MYSQLI_ASSOC ) : [];

  return [
    'categories' => $categories,
    'statuses' => $statuses
  ];
}

function ssp_change_reference_data( $con, $person ) {
  $assets_stmt = mysqli_prepare( $con, "
        SELECT a.id, a.objectid, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        WHERE a.owner = ?
        ORDER BY a.objectid ASC
    " );
  mysqli_stmt_bind_param( $assets_stmt, "i", $person['id'] );
  mysqli_stmt_execute( $assets_stmt );
  $assets_result = mysqli_stmt_get_result( $assets_stmt );
  $assets = mysqli_fetch_all( $assets_result, MYSQLI_ASSOC );
  mysqli_stmt_close( $assets_stmt );

  $categories_result = mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'CHANGE' ORDER BY name ASC" );
  $categories = $categories_result ? mysqli_fetch_all( $categories_result, MYSQLI_ASSOC ) : [];
  $subcategories_result = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" );
  $subcategories = $subcategories_result ? mysqli_fetch_all( $subcategories_result, MYSQLI_ASSOC ) : [];
  $templates_result = mysqli_query( $con, "
        SELECT id, name, changerequesttype, persongroupid, categoryid, subcategoryid, description, commenttext
        FROM itsm_core_templates
        WHERE type = 'CHANGE'
        ORDER BY name ASC
    " );
  $templates = $templates_result ? mysqli_fetch_all( $templates_result, MYSQLI_ASSOC ) : [];
  $allowed_group_ids = array_map(
    function( $row ) {
      return (int)$row['persongroup'];
    },
    $person['persongroups'] ?? []
  );
  $templates = array_values( array_filter(
    $templates,
    function( $template ) use ( $allowed_group_ids ) {
      if ( empty( $template['persongroupid'] ) ) {
        return true;
      }

      return in_array( (int)$template['persongroupid'], $allowed_group_ids, true );
    }
  ) );

  return [
    'assets' => $assets,
    'categories' => $categories,
    'subcategories' => $subcategories,
    'templates' => $templates
  ];
}

function ssp_default_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function ssp_person_is_manager( $person ) {
  return ssp_person_has_group_name( $person, 'SSP_Manager' );
}

function ssp_scope_from_request( $person, $requested_scope ) {
  if ( $requested_scope === 'customer' && ssp_person_is_manager( $person ) ) {
    return 'customer';
  }

  return 'mine';
}

function ssp_incident_scope_clause( $scope ) {
  if ( $scope === 'customer' ) {
    return 'i.customerid = ?';
  }

  return 'i.personid = ?';
}

function ssp_change_scope_clause( $scope ) {
  if ( $scope === 'customer' ) {
    return 'c.customerid = ?';
  }

  return 'c.personid = ?';
}

function ssp_dashboard_counts( $con, $person ) {
  $person_id = (int)$person['id'];
  $counts = [
    'open_incidents' => 0,
    'open_changes' => 0,
    'open_change_requests' => 0,
    'assigned_assets' => 0,
    'customer_open_incidents' => 0,
    'customer_open_changes' => 0
  ];

  $stmt = mysqli_prepare( $con, "
        SELECT COUNT(*)
        FROM itsm_im_incidents i
        LEFT JOIN itsm_core_status s ON i.statusid = s.id
        WHERE i.personid = ? AND IFNULL(s.closed, 0) = 0
    " );
  mysqli_stmt_bind_param( $stmt, "i", $person_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $counts['open_incidents'] );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  $stmt = mysqli_prepare( $con, "
        SELECT COUNT(*)
        FROM itsm_cm_changes c
        LEFT JOIN itsm_core_status s ON c.statusid = s.id
        WHERE c.personid = ?
          AND (
            c.approvalstate = 'request'
            OR (c.approvalstate = 'approved' AND c.closed = 0 AND IFNULL(s.closed, 0) = 0)
          )
    " );
  mysqli_stmt_bind_param( $stmt, "i", $person_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $counts['open_changes'] );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  $stmt = mysqli_prepare( $con, "SELECT COUNT(*) FROM itsm_cm_changes WHERE personid = ? AND approvalstate = 'request'" );
  mysqli_stmt_bind_param( $stmt, "i", $person_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $counts['open_change_requests'] );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  $stmt = mysqli_prepare( $con, "SELECT COUNT(*) FROM itsm_am_assets WHERE owner = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $person_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $counts['assigned_assets'] );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  if ( ssp_person_is_manager( $person ) ) {
    $customer_id = (int)$person['customerid'];

    $stmt = mysqli_prepare( $con, "
        SELECT COUNT(*)
        FROM itsm_im_incidents i
        LEFT JOIN itsm_core_status s ON i.statusid = s.id
        WHERE i.customerid = ? AND IFNULL(s.closed, 0) = 0
    " );
    mysqli_stmt_bind_param( $stmt, "i", $customer_id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_bind_result( $stmt, $counts['customer_open_incidents'] );
    mysqli_stmt_fetch( $stmt );
    mysqli_stmt_close( $stmt );

    $stmt = mysqli_prepare( $con, "
        SELECT COUNT(*)
        FROM itsm_cm_changes c
        LEFT JOIN itsm_core_status s ON c.statusid = s.id
        WHERE c.customerid = ?
          AND (
            c.approvalstate = 'request'
            OR (c.approvalstate = 'approved' AND c.closed = 0 AND IFNULL(s.closed, 0) = 0)
          )
    " );
    mysqli_stmt_bind_param( $stmt, "i", $customer_id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_bind_result( $stmt, $counts['customer_open_changes'] );
    mysqli_stmt_fetch( $stmt );
    mysqli_stmt_close( $stmt );
  }

  return $counts;
}

function ssp_person_has_group_name( $person, $group_name ) {
  foreach ( $person['persongroups'] ?? [] as $group ) {
    if ( strcasecmp( (string)( $group['groupname'] ?? '' ), (string)$group_name ) === 0 ) {
      return true;
    }
  }

  return false;
}

function ssp_find_group_id_by_name( $person, $group_name ) {
  foreach ( $person['persongroups'] ?? [] as $group ) {
    if ( strcasecmp( (string)( $group['groupname'] ?? '' ), (string)$group_name ) === 0 ) {
      return (int)$group['persongroup'];
    }
  }

  return 0;
}

function ssp_find_group_name_by_id( $person, $group_id ) {
  foreach ( $person['persongroups'] ?? [] as $group ) {
    if ( (int)( $group['persongroup'] ?? 0 ) === (int)$group_id ) {
      return (string)( $group['groupname'] ?? '' );
    }
  }

  return '';
}

function ssp_extract_template_variables( $template ) {
  $matches = [];
  $sources = [
    $template['description'] ?? '',
    $template['commenttext'] ?? '',
    $template['name'] ?? ''
  ];

  foreach ( $sources as $source ) {
    preg_match_all( '/%([a-zA-Z0-9_]+)%/', (string)$source, $found );
    if ( !empty( $found[1] ) ) {
      foreach ( $found[1] as $variable ) {
        $matches[ $variable ] = true;
      }
    }
  }

  return array_keys( $matches );
}

function ssp_template_has_variables( $template ) {
  return count( ssp_extract_template_variables( $template ) ) > 0;
}

function ssp_template_variable_label( $variable ) {
  return ucwords( str_replace( '_', ' ', $variable ) );
}

function ssp_apply_template_variables( $text, $values ) {
  return preg_replace_callback(
    '/%([a-zA-Z0-9_]+)%/',
    function( $matches ) use ( $values ) {
      $key = $matches[1];
      return $values[ $key ] ?? '';
    },
    (string)$text
  );
}

function ssp_attachment_uploaded_file_available( $field_name = 'attachment' ) {
  return isset( $_FILES[$field_name] )
    && is_array( $_FILES[$field_name] )
    && (int)$_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE;
}

function ssp_attachment_upload_errors( $field_name = 'attachment' ) {
  if ( !ssp_attachment_uploaded_file_available( $field_name ) ) {
    return [];
  }

  $file = $_FILES[$field_name];
  if ( (int)$file['error'] !== UPLOAD_ERR_OK ) {
    return [ 'Bijlage uploaden mislukt. Upload foutcode: ' . (int)$file['error'] ];
  }

  $validation = itsm_uploaded_attachment_validation( $file );
  return $validation['ok'] ? [] : [ $validation['error'] ];
}

function ssp_attachment_save_upload( $con, $task_type, $task_id, $person_id, $comment_type, $comment_id, $field_name = 'attachment' ) {
  if ( !ssp_attachment_uploaded_file_available( $field_name ) ) {
    return 0;
  }

  $file = $_FILES[$field_name];
  $validation = itsm_uploaded_attachment_validation( $file );
  if ( !$validation['ok'] ) {
    return 0;
  }
  $content = $validation['content'];

  $task_id = (int)$task_id;
  $person_id = (int)$person_id;
  $comment_id = (int)$comment_id;
  $filename = basename( (string)$file['name'] );
  $mimetype = $validation['mime'];
  $filesize = strlen( $content );
  $uploaded_by = null;
  $internal_only = 0;
  $null_blob = null;

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_attachments
      (tasktype, taskid, commenttype, commentid, filename, mimetype, filesize, content, uploadedby, uploadedbyperson, internalonly)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  " );
  mysqli_stmt_bind_param(
    $stmt,
    'sisissibiii',
    $task_type,
    $task_id,
    $comment_type,
    $comment_id,
    $filename,
    $mimetype,
    $filesize,
    $null_blob,
    $uploaded_by,
    $person_id,
    $internal_only
  );
  mysqli_stmt_send_long_data( $stmt, 7, $content );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'portal_attachment_insert_failed', mysqli_stmt_error( $stmt ) );
  }
  $id = mysqli_insert_id( $con );
  mysqli_stmt_close( $stmt );

  return $id;
}

function ssp_attachment_format_filesize( $bytes ) {
  $bytes = (int)$bytes;
  if ( $bytes >= 1048576 ) {
    return round( $bytes / 1048576, 1 ) . ' MB';
  }
  if ( $bytes >= 1024 ) {
    return round( $bytes / 1024, 1 ) . ' KB';
  }

  return $bytes . ' B';
}

function ssp_attachment_load_for_task( $con, $task_type, $task_id ) {
  $stmt = mysqli_prepare( $con, "
    SELECT id, tasktype, taskid, commenttype, commentid, filename, mimetype, filesize, createdat
    FROM itsm_core_attachments
    WHERE tasktype = ? AND taskid = ? AND internalonly = 0
    ORDER BY createdat DESC, id DESC
  " );
  mysqli_stmt_bind_param( $stmt, 'si', $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rows = mysqli_fetch_all( $result, MYSQLI_ASSOC );
  mysqli_stmt_close( $stmt );

  return $rows;
}

function ssp_attachment_group_by_comment( $attachments ) {
  $grouped = [];
  foreach ( $attachments as $attachment ) {
    $key = (string)( $attachment['commentid'] ?? '' );
    if ( $key === '' || $key === '0' ) {
      continue;
    }
    if ( !isset( $grouped[$key] ) ) {
      $grouped[$key] = [];
    }
    $grouped[$key][] = $attachment;
  }

  return $grouped;
}

function ssp_attachment_render_links( $attachments ) {
  if ( empty( $attachments ) ) {
    return '';
  }

  $html = '<div class="ssp-attachment-list">';
  foreach ( $attachments as $attachment ) {
    $html .= '<a class="ssp-attachment-link" href="download_attachment.php?id=' . htmlspecialchars( (string)$attachment['id'] ) . '">';
    $html .= '<i class="fa-solid fa-paperclip"></i> ' . htmlspecialchars( $attachment['filename'] );
    $html .= ' <span>(' . htmlspecialchars( ssp_attachment_format_filesize( $attachment['filesize'] ) ) . ')</span>';
    $html .= '</a>';
    if ( itsm_attachment_mimetype_can_inline( $attachment['mimetype'] ?? '' ) ) {
      $html .= '<img class="ssp-inline-image" src="download_attachment.php?id=' . htmlspecialchars( (string)$attachment['id'] ) . '&amp;inline=1" alt="' . htmlspecialchars( $attachment['filename'] ) . '">';
    }
  }
  $html .= '</div>';

  return $html;
}

function ssp_attachment_render_upload_field() {
  ?>
  <div class="ssp-field">
    <label for="attachment">Bijlage</label>
    <input id="attachment" name="attachment" type="file">
  </div>
  <?php
}

function ssp_can_access_task( $person, $task ) {
  if ( !$task ) {
    return false;
  }

  if ( (int)( $task['personid'] ?? 0 ) === (int)$person['id'] ) {
    return true;
  }

  return ssp_person_is_manager( $person ) && (int)( $task['customerid'] ?? 0 ) === (int)$person['customerid'];
}

function ssp_load_incident_for_person( $con, $person, $incident_id ) {
  $incident_id = (int)$incident_id;
  if ( $incident_id <= 0 ) {
    return null;
  }

  $stmt = mysqli_prepare( $con, "
    SELECT i.*, cat.name AS category_name, sub.name AS subcategory_name, s.name AS status_name, a.objectid AS asset_objectid, t.type AS asset_type
    FROM itsm_im_incidents i
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON i.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_am_assets a ON i.assetid = a.id
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE i.id = ?
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'i', $incident_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $incident = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !ssp_can_access_task( $person, $incident ) ) {
    return null;
  }

  return $incident;
}

function ssp_attachment_can_access( $con, $person, $attachment_id ) {
  $stmt = mysqli_prepare( $con, "
    SELECT id, tasktype, taskid, internalonly
    FROM itsm_core_attachments
    WHERE id = ?
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'i', $attachment_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $attachment = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  if ( !$attachment || (int)$attachment['internalonly'] === 1 ) {
    return false;
  }

  if ( $attachment['tasktype'] === 'incident' ) {
    $stmt = mysqli_prepare( $con, "SELECT id, customerid, personid FROM itsm_im_incidents WHERE id = ? LIMIT 1" );
  } elseif ( $attachment['tasktype'] === 'change' ) {
    $stmt = mysqli_prepare( $con, "SELECT id, customerid, personid FROM itsm_cm_changes WHERE id = ? LIMIT 1" );
  } else {
    return false;
  }

  $task_id = (int)$attachment['taskid'];
  mysqli_stmt_bind_param( $stmt, 'i', $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $task = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  return ssp_can_access_task( $person, $task );
}
