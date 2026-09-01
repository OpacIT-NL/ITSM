<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/operator_security_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  http_response_code( 400 );
  exit( 'Invalid attachment' );
}

$attachment_id = (int)$_GET['id'];
$stmt = mysqli_prepare( $con, "SELECT tasktype, taskid, filename, mimetype, filesize FROM itsm_core_attachments WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, 'i', $attachment_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$attachment = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$attachment || !itsm_operator_can_access_attachment( itsm_current_operator_security_context( $con ), $attachment ) ) {
  http_response_code( 404 );
  exit( 'Attachment not found' );
}

$content_stmt = mysqli_prepare( $con, "SELECT content FROM itsm_core_attachments WHERE id = ? LIMIT 1" );
mysqli_stmt_bind_param( $content_stmt, 'i', $attachment_id );
mysqli_stmt_execute( $content_stmt );
mysqli_stmt_bind_result( $content_stmt, $content );
mysqli_stmt_fetch( $content_stmt );
mysqli_stmt_close( $content_stmt );

$filename = (string)$attachment['filename'];
$mimetype = (string)$attachment['mimetype'];
$filesize = (int)$attachment['filesize'];
$safe_filename = basename( (string)$filename );
$safe_filename = preg_replace( '/[\x00-\x1F\x7F"\\\\]/', '_', $safe_filename );
$safe_filename = $safe_filename !== '' ? $safe_filename : 'attachment';
$mimetype = preg_match( '#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', $mimetype )
  ? strtolower( $mimetype )
  : 'application/octet-stream';
$inline_mimetypes = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
$is_inline_image = isset( $_GET['inline'] ) && in_array( strtolower( $mimetype ), $inline_mimetypes, true );
header( 'Content-Type: ' . ( $mimetype ?: 'application/octet-stream' ) );
header( 'Content-Length: ' . (int)$filesize );
header( 'Content-Disposition: ' . ( $is_inline_image ? 'inline' : 'attachment' ) . '; filename="' . $safe_filename . '"' );
header( 'X-Content-Type-Options: nosniff' );
header( "Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:" );
echo $content;
exit;
