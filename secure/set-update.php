<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/../version.php' );
require_once( __DIR__ . '/include/markdown_helpers.php' );

const ITSM_UPDATE_REPO_BASE = 'https://repo.opacit.nl/itsm';

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}

if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];

$stmt = mysqli_prepare( $con, "SELECT isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $isadmin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( (int)$isadmin !== 1 ) {
  header( 'Location: index.php' );
  exit;
}

function itsm_update_normalize_version( $value ) {
  $value = trim( (string)$value );
  if ( preg_match( '/v?\d+\.\d+\.\d+/', $value, $matches ) ) {
    return 'v' . ltrim( $matches[0], 'vV' );
  }
  return '';
}

function itsm_update_compare_versions( $left, $right ) {
  return version_compare( ltrim( $left, 'vV' ), ltrim( $right, 'vV' ) );
}

function itsm_update_http_get( $url, &$error = null ) {
  $error = null;

  if ( function_exists( 'curl_init' ) ) {
    $curl = curl_init( $url );
    curl_setopt_array( $curl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_USERAGENT => 'ITSM updater',
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2
    ] );
    $body = curl_exec( $curl );
    $status = (int)curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if ( $body === false ) {
      $error = curl_error( $curl );
    }
    curl_close( $curl );

    if ( $body !== false && $status >= 200 && $status < 300 ) {
      return $body;
    }

    if ( $error === null ) {
      $error = 'HTTP status ' . $status;
    }
    return false;
  }

  $context = stream_context_create( [
    'http' => [
      'follow_location' => 1,
      'ignore_errors' => true,
      'timeout' => 30,
      'header' => "User-Agent: ITSM updater\r\n"
    ]
  ] );
  $body = @file_get_contents( $url, false, $context );

  if ( $body === false ) {
    $error = 'Unable to download ' . $url;
    return false;
  }

  return $body;
}

function itsm_update_parse_versions( $body ) {
  $versions = [];
  $latest = '';
  $decoded = json_decode( $body, true );

  if ( is_array( $decoded ) ) {
    if ( isset( $decoded['latest'] ) ) {
      $latest = itsm_update_normalize_version( $decoded['latest'] );
    }
    if ( isset( $decoded['versions'] ) && is_array( $decoded['versions'] ) ) {
      foreach ( $decoded['versions'] as $entry ) {
        $normalized = itsm_update_normalize_version( $entry );
        if ( $normalized !== '' ) {
          $versions[] = $normalized;
        }
      }
    } else {
      foreach ( $decoded as $entry ) {
        if ( is_string( $entry ) || is_numeric( $entry ) ) {
          $normalized = itsm_update_normalize_version( $entry );
          if ( $normalized !== '' ) {
            $versions[] = $normalized;
          }
        }
      }
    }
  } else {
    foreach ( preg_split( '/[\r\n,; ]+/', $body ) as $entry ) {
      $normalized = itsm_update_normalize_version( $entry );
      if ( $normalized !== '' ) {
        $versions[] = $normalized;
      }
    }
  }

  if ( $latest !== '' ) {
    $versions[] = $latest;
  }

  $versions = array_values( array_unique( $versions ) );
  usort( $versions, 'itsm_update_compare_versions' );

  if ( $latest === '' && !empty( $versions ) ) {
    $latest = $versions[count( $versions ) - 1];
  }

  return [ $versions, $latest ];
}

function itsm_update_pending_versions( $versions, $current, $latest ) {
  $pending = [];
  foreach ( $versions as $repo_version ) {
    if (
      itsm_update_compare_versions( $repo_version, $current ) > 0 &&
      ( $latest === '' || itsm_update_compare_versions( $repo_version, $latest ) <= 0 )
    ) {
      $pending[] = $repo_version;
    }
  }
  return $pending;
}

function itsm_update_safe_extract_zip( $zip_path, $destination ) {
  if ( !class_exists( 'ZipArchive' ) ) {
    throw new RuntimeException( 'ZipArchive is not available on this server.' );
  }

  $zip = new ZipArchive();
  if ( $zip->open( $zip_path ) !== true ) {
    throw new RuntimeException( 'Could not open downloaded base.zip.' );
  }

  $root = realpath( $destination );
  if ( $root === false ) {
    $zip->close();
    throw new RuntimeException( 'Application root could not be resolved.' );
  }

  for ( $i = 0; $i < $zip->numFiles; $i++ ) {
    $entry = $zip->getNameIndex( $i );
    if ( $entry === false || $entry === '' ) {
      continue;
    }
    if ( strpos( $entry, "\0" ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $entry ) || preg_match( '#^[A-Za-z]:#', $entry ) || substr( $entry, 0, 1 ) === '/' ) {
      $zip->close();
      throw new RuntimeException( 'base.zip contains an unsafe path: ' . $entry );
    }

    $target = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $entry );

    if ( substr( $entry, -1 ) === '/' ) {
      if ( !is_dir( $target ) && !mkdir( $target, 0755, true ) ) {
        $zip->close();
        throw new RuntimeException( 'Could not create directory: ' . $entry );
      }
      continue;
    }

    $target_dir = dirname( $target );
    if ( !is_dir( $target_dir ) && !mkdir( $target_dir, 0755, true ) ) {
      $zip->close();
      throw new RuntimeException( 'Could not create directory for: ' . $entry );
    }

    $contents = $zip->getFromIndex( $i );
    if ( $contents === false || file_put_contents( $target, $contents ) === false ) {
      $zip->close();
      throw new RuntimeException( 'Could not write file: ' . $entry );
    }
  }

  $zip->close();
}

function itsm_update_run_sql( $con, $sql ) {
  if ( trim( $sql ) === '' ) {
    return;
  }

  if ( !mysqli_multi_query( $con, $sql ) ) {
    throw new RuntimeException( mysqli_error( $con ) );
  }

  do {
    if ( $result = mysqli_store_result( $con ) ) {
      mysqli_free_result( $result );
    }
    if ( mysqli_more_results( $con ) && !mysqli_next_result( $con ) ) {
      throw new RuntimeException( mysqli_error( $con ) );
    }
  } while ( mysqli_more_results( $con ) );
}

function itsm_update_remove_tree( $path ) {
  if ( $path === '' || !file_exists( $path ) ) {
    return;
  }

  if ( is_file( $path ) || is_link( $path ) ) {
    @unlink( $path );
    return;
  }

  foreach ( scandir( $path ) ?: [] as $entry ) {
    if ( $entry === '.' || $entry === '..' ) {
      continue;
    }
    itsm_update_remove_tree( $path . DIRECTORY_SEPARATOR . $entry );
  }
  @rmdir( $path );
}

$current_version = itsm_update_normalize_version( $version );
$manifest_error = null;
$manifest_body = itsm_update_http_get( ITSM_UPDATE_REPO_BASE . '/version.json', $manifest_error );
$available_versions = [];
$latest_version = '';
$pending_versions = [];
$changelogs = [];
$message = '';
$error = '';
$update_log = [];

if ( $manifest_body === false ) {
  $error = 'Could not check for updates: ' . $manifest_error;
} else {
  [ $available_versions, $latest_version ] = itsm_update_parse_versions( $manifest_body );
  $pending_versions = itsm_update_pending_versions( $available_versions, $current_version, $latest_version );

  foreach ( array_reverse( $pending_versions ) as $repo_version ) {
    $changelog_error = null;
    $changelog = itsm_update_http_get( ITSM_UPDATE_REPO_BASE . '/' . rawurlencode( $repo_version ) . '/changelog.md', $changelog_error );
    $changelogs[$repo_version] = $changelog === false ? 'Changelog could not be loaded: ' . $changelog_error : $changelog;
  }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['run_update'] ) ) {
  if ( empty( $pending_versions ) || $latest_version === '' ) {
    $error = 'No update is available.';
  } else {
    $tmp_dir = '';
    try {
      $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itsm_update_' . bin2hex( random_bytes( 8 ) );
      if ( !mkdir( $tmp_dir, 0700, true ) ) {
        throw new RuntimeException( 'Could not create temporary update directory.' );
      }

      $sql_files = [];
      foreach ( $pending_versions as $repo_version ) {
        $sql_error = null;
        $sql = itsm_update_http_get( ITSM_UPDATE_REPO_BASE . '/' . rawurlencode( $repo_version ) . '/update_db.sql', $sql_error );
        if ( $sql === false ) {
          $update_log[] = 'No database update found for ' . $repo_version . '.';
          continue;
        }
        $sql_files[$repo_version] = $sql;
        $update_log[] = 'Downloaded database update for ' . $repo_version . '.';
      }

      $zip_error = null;
      $zip_body = itsm_update_http_get( ITSM_UPDATE_REPO_BASE . '/' . rawurlencode( $latest_version ) . '/base.zip', $zip_error );
      if ( $zip_body === false ) {
        throw new RuntimeException( 'Could not download base.zip for ' . $latest_version . ': ' . $zip_error );
      }
      $zip_path = $tmp_dir . DIRECTORY_SEPARATOR . 'base.zip';
      if ( file_put_contents( $zip_path, $zip_body ) === false ) {
        throw new RuntimeException( 'Could not save downloaded base.zip.' );
      }
      $update_log[] = 'Downloaded application files for ' . $latest_version . '.';

      foreach ( $sql_files as $repo_version => $sql ) {
        itsm_update_run_sql( $con, $sql );
        $update_log[] = 'Applied database update for ' . $repo_version . '.';
      }

      itsm_update_safe_extract_zip( $zip_path, dirname( __DIR__ ) );
      $update_log[] = 'Installed application files from ' . $latest_version . '.';

      $message = 'ITSM has been updated to ' . $latest_version . '.';
      $current_version = $latest_version;
      $pending_versions = [];
    } catch ( Throwable $exception ) {
      $error = 'Update failed: ' . $exception->getMessage();
    } finally {
      itsm_update_remove_tree( $tmp_dir );
    }
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/settings.php'); ?>
<div class="content">
  <div class="module-section">
    <h1><?= htmlspecialchars(t('Update ITSM')) ?></h1>
    <p class="info-note"><?= htmlspecialchars(t('Controleer op nieuwe ITSM versies en installeer updates vanaf repo.opacit.nl.')) ?></p>

    <?php if ( $message !== '' ): ?>
    <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ( $error !== '' ): ?>
    <p class="ssp-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="update-status-grid">
      <div class="update-status-card">
        <strong><?= htmlspecialchars(t('Huidige versie')) ?></strong>
        <?= htmlspecialchars($current_version !== '' ? $current_version : $version) ?>
      </div>
      <div class="update-status-card">
        <strong><?= htmlspecialchars(t('Nieuwste versie')) ?></strong>
        <?= htmlspecialchars($latest_version !== '' ? $latest_version : t('Onbekend')) ?>
      </div>
      <div class="update-status-card">
        <strong><?= htmlspecialchars(t('Update status')) ?></strong>
        <?= empty( $pending_versions ) ? htmlspecialchars(t('Geen update beschikbaar')) : htmlspecialchars(t('Update beschikbaar')) ?>
      </div>
    </div>

    <?php if ( !empty( $pending_versions ) ): ?>
    <div class="form-card form-card-wide">
      <h2><?= htmlspecialchars(t('Te installeren versies')) ?></h2>
      <p class="info-note"><?= htmlspecialchars(t('Alle database-updates worden op volgorde uitgevoerd. Alleen de base.zip van de nieuwste versie wordt geinstalleerd.')) ?></p>
      <div class="update-version-list">
        <?php foreach ( $pending_versions as $repo_version ): ?>
        <span><?= htmlspecialchars($repo_version) ?></span>
        <?php endforeach; ?>
      </div>
      <form method="post" class="form-actions">
        <button type="submit" name="run_update" value="1" class="btn-primary"><?= htmlspecialchars(t('Update ITSM')) ?></button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ( !empty( $update_log ) ): ?>
    <pre class="update-log"><?= htmlspecialchars( implode( "\n", $update_log ) ) ?></pre>
    <?php endif; ?>

    <?php if ( !empty( $changelogs ) ): ?>
    <div class="update-changelog">
      <?php foreach ( $changelogs as $repo_version => $changelog ): ?>
      <div class="update-changelog-entry">
        <h2><?= htmlspecialchars($repo_version) ?></h2>
        <?= markdown_to_html( $changelog ) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
