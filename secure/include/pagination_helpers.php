<?php
function itsm_pagination_state( $per_page = 100 ) {
  $page = isset( $_GET['page'] ) && is_numeric( $_GET['page'] ) ? max( 1, (int)$_GET['page'] ) : 1;
  $per_page = max( 1, (int)$per_page );
  return [
    'page' => $page,
    'per_page' => $per_page,
    'offset' => ( $page - 1 ) * $per_page
  ];
}

function itsm_pagination_count( $con, $sql ) {
  $count_result = mysqli_query( $con, 'SELECT COUNT(*) AS total FROM (' . $sql . ') itsm_counted_rows' );
  if ( !$count_result ) {
    return 0;
  }
  $row = mysqli_fetch_assoc( $count_result );
  return (int)( $row['total'] ?? 0 );
}

function itsm_pagination_limit_sql( $sql, $state ) {
  return $sql . ' LIMIT ' . (int)$state['per_page'] . ' OFFSET ' . (int)$state['offset'];
}

function itsm_pagination_url( $page ) {
  $params = $_GET;
  if ( $page <= 1 ) {
    unset( $params['page'] );
  } else {
    $params['page'] = (string)$page;
  }
  $query = http_build_query( $params );
  return basename( $_SERVER['PHP_SELF'] ) . ( $query !== '' ? '?' . $query : '' );
}

function itsm_render_pagination( $total, $state ) {
  $per_page = (int)$state['per_page'];
  $page = (int)$state['page'];
  $pages = max( 1, (int)ceil( $total / $per_page ) );
  $start = $total > 0 ? ( ( $page - 1 ) * $per_page ) + 1 : 0;
  $end = min( $total, $page * $per_page );
  ?>
  <nav class="table-pagination" aria-label="<?= htmlspecialchars(t('Paginering')) ?>">
    <span class="table-pagination-summary"><?= htmlspecialchars($start . '-' . $end . ' / ' . $total) ?></span>
    <?php if ( $pages > 1 ): ?>
      <a class="btn<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars(itsm_pagination_url($page - 1)) ?>"><?= htmlspecialchars(t('Vorige')) ?></a>
      <?php
      $first_page = max( 1, $page - 2 );
      $last_page = min( $pages, $page + 2 );
      for ( $i = $first_page; $i <= $last_page; $i++ ):
      ?>
        <a class="btn<?= $i === $page ? ' is-active' : '' ?>" href="<?= htmlspecialchars(itsm_pagination_url($i)) ?>"><?= htmlspecialchars((string)$i) ?></a>
      <?php endfor; ?>
      <a class="btn<?= $page >= $pages ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars(itsm_pagination_url($page + 1)) ?>"><?= htmlspecialchars(t('Volgende')) ?></a>
    <?php endif; ?>
  </nav>
  <?php
}
?>
