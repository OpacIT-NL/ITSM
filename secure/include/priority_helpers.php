<?php

function priority_load_reference_data( $con ) {
  return [
    'impacts' => mysqli_query( $con, "SELECT id, name FROM itsm_core_impacts WHERE active = 1 ORDER BY sortorder ASC, name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'urgencies' => mysqli_query( $con, "SELECT id, name FROM itsm_core_urgencies WHERE active = 1 ORDER BY sortorder ASC, name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'priorities' => mysqli_query( $con, "SELECT id, name FROM itsm_core_priorities WHERE active = 1 ORDER BY sortorder ASC, name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'priority_matrix' => mysqli_query( $con, "
      SELECT m.impactid, m.urgencyid, m.priorityid, p.name AS priorityname
      FROM itsm_core_prioritymatrix m
      INNER JOIN itsm_core_priorities p ON m.priorityid = p.id
      WHERE p.active = 1
    " )->fetch_all( MYSQLI_ASSOC )
  ];
}

function priority_merge_reference_data( $con, $reference_data ) {
  return array_merge( $reference_data, priority_load_reference_data( $con ) );
}

function priority_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function priority_calculate_id( $reference_data, $impact_id, $urgency_id ) {
  if ( empty( $impact_id ) || empty( $urgency_id ) ) {
    return null;
  }

  foreach ( $reference_data['priority_matrix'] ?? [] as $row ) {
    if ( (int)$row['impactid'] === (int)$impact_id && (int)$row['urgencyid'] === (int)$urgency_id ) {
      return (int)$row['priorityid'];
    }
  }

  return null;
}

function priority_validate_selection( $data, $reference_data ) {
  $impact_id = !empty( $data['impactid'] ) ? (int)$data['impactid'] : null;
  $urgency_id = !empty( $data['urgencyid'] ) ? (int)$data['urgencyid'] : null;
  $priority_id = priority_calculate_id( $reference_data, $impact_id, $urgency_id );
  $errors = [];

  if ( $impact_id && !$urgency_id ) {
    $errors[] = 'Selecteer ook een urgentie bij de gekozen impact.';
  }
  if ( $urgency_id && !$impact_id ) {
    $errors[] = 'Selecteer ook een impact bij de gekozen urgentie.';
  }
  if ( $impact_id && !priority_find_by_id( $reference_data['impacts'] ?? [], $impact_id ) ) {
    $errors[] = 'Selecteer een geldige impact.';
  }
  if ( $urgency_id && !priority_find_by_id( $reference_data['urgencies'] ?? [], $urgency_id ) ) {
    $errors[] = 'Selecteer een geldige urgentie.';
  }
  if ( $impact_id && $urgency_id && !$priority_id ) {
    $errors[] = t('Er is geen prioriteit ingesteld voor deze Impact/Urgency combinatie.');
  }

  return [
    'errors' => $errors,
    'impactid' => $impact_id,
    'urgencyid' => $urgency_id,
    'priorityid' => $priority_id
  ];
}

function priority_name_by_id( $reference_data, $priority_id ) {
  $priority = priority_find_by_id( $reference_data['priorities'] ?? [], $priority_id );
  return $priority['name'] ?? '';
}
