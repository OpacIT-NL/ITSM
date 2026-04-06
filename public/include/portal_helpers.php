<?php

function ssp_require_login( $con ) {
  session_start();

  if ( !isset( $_SESSION['ssploggedin'], $_SESSION['id'] ) ) {
    header( 'Location: login.php' );
    exit;
  }

  if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
    session_unset();
    session_destroy();
    header( 'Location: login.php?expired=1' );
    exit;
  }

  $person_id = (int)$_SESSION['id'];
  $stmt = mysqli_prepare( $con, "
        SELECT p.id, p.customerid, p.firstname, p.lastname, p.email, p.phone, p.allowssp, c.name AS customer_name
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
    session_unset();
    session_destroy();
    header( 'Location: login.php' );
    exit;
  }

  $_SESSION['expires_at'] = time() + ( 12 * 60 * 60 );
  $_SESSION['name'] = trim( ($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '') );
  $_SESSION['email'] = $person['email'] ?? '';

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
    'assets' => [ 'href' => 'assets.php', 'label' => 'Assets', 'icon' => 'fa-laptop' ],
    'new_incident' => [ 'href' => 'new_incident.php', 'label' => 'Incident melden', 'icon' => 'fa-phone' ],
    'new_change' => [ 'href' => 'new_change.php', 'label' => 'Wijziging aanvragen', 'icon' => 'fa-file-circle-plus' ]
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
  echo "        <p>Ingelogd als {$name}</p>\n";
  echo "      </div>\n";
  echo "      <div class=\"ssp-topbar-actions\">\n";
  echo "        <a class=\"ssp-ghost-link\" href=\"logout.php\">Uitloggen <i class=\"fa-solid fa-right-from-bracket\"></i></a>\n";
  echo "      </div>\n";
  echo "    </header>\n";
}

function ssp_render_footer() {
  echo "  </main>\n";
  echo "</div>\n";
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

  $categories_result = mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'INCIDENT' ORDER BY name ASC" );
  $categories = $categories_result ? mysqli_fetch_all( $categories_result, MYSQLI_ASSOC ) : [];
  $subcategories_result = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" );
  $subcategories = $subcategories_result ? mysqli_fetch_all( $subcategories_result, MYSQLI_ASSOC ) : [];
  $statuses_result = mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'INCIDENT' ORDER BY name ASC" );
  $statuses = $statuses_result ? mysqli_fetch_all( $statuses_result, MYSQLI_ASSOC ) : [];

  return [
    'assets' => $assets,
    'categories' => $categories,
    'subcategories' => $subcategories,
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

function ssp_dashboard_counts( $con, $person ) {
  $person_id = (int)$person['id'];
  $counts = [
    'open_incidents' => 0,
    'open_changes' => 0,
    'open_change_requests' => 0,
    'assigned_assets' => 0
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
