<?php

function news_require_firstline_authorization( $con, $logged_in_user, $redirect = 'index.php' ) {
  $stmt = mysqli_prepare( $con, "SELECT firstlineincidents FROM itsm_ob_operators WHERE username = ?" );
  mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $firstlineincidents );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  if ( (int)$firstlineincidents !== 1 ) {
    header( 'Location: ' . $redirect );
    exit();
  }
}

function news_type_options() {
  return [
    'major_outage' => 'Grote verstoring',
    'outage' => 'Verstoring',
    'maintenance' => 'Onderhoud',
    'general' => 'Algemeen Nieuws'
  ];
}

function news_type_label( $type ) {
  $options = news_type_options();
  return $options[ $type ] ?? $type;
}

function news_type_css_class( $type ) {
  $map = [
    'major_outage' => 'news-major-outage',
    'outage' => 'news-outage',
    'maintenance' => 'news-maintenance',
    'general' => 'news-general'
  ];

  return $map[ $type ] ?? 'news-general';
}

function news_fetch_items( $con, $scope = 'all', $limit = 0 ) {
  $where = '';
  if ( $scope === 'login' ) {
    $where = 'WHERE showlogin = 1';
  } elseif ( $scope === 'public' ) {
    $where = 'WHERE showssp = 1';
  } elseif ( $scope === 'secure' ) {
    $where = 'WHERE showoperatorhome = 1';
  }

  $sql = "
    SELECT n.*, o.firstname AS creator_firstname, o.lastname AS creator_lastname
    FROM itsm_core_news n
    LEFT JOIN itsm_ob_operators o ON n.createdby = o.id
    $where
    ORDER BY n.createdat DESC, n.id DESC
  ";

  if ( $limit > 0 ) {
    $sql .= ' LIMIT ' . (int)$limit;
  }

  $result = mysqli_query( $con, $sql );
  return $result ? mysqli_fetch_all( $result, MYSQLI_ASSOC ) : [];
}

function news_render_cards( $items ) {
  if ( empty( $items ) ) {
    return '<div class="kb-empty">Geen nieuwsberichten beschikbaar.</div>';
  }

  ob_start();
  ?>
  <div class="news-card-list">
    <?php foreach ( $items as $item ): ?>
    <article class="news-card <?= htmlspecialchars(news_type_css_class($item['newstype'])) ?>">
      <div class="news-card-meta">
        <strong><?= htmlspecialchars(news_type_label($item['newstype'])) ?></strong>
        <span><?= htmlspecialchars($item['createdat']) ?></span>
      </div>
      <h3><?= htmlspecialchars($item['title']) ?></h3>
      <div class="news-card-body"><?= nl2br(htmlspecialchars($item['message'])) ?></div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}

function news_render_banners( $items ) {
  if ( empty( $items ) ) {
    return '';
  }

  ob_start();
  ?>
  <div class="news-banner-stack">
    <?php foreach ( $items as $item ): ?>
    <a class="news-banner news-banner-link <?= htmlspecialchars(news_type_css_class($item['newstype'])) ?>" href="/status.php">
      <strong><?= htmlspecialchars(news_type_label($item['newstype'])) ?>:</strong>
      <span><?= htmlspecialchars($item['title']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}
