<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );

if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  http_response_code( 400 );
  exit( 'Invalid attachment' );
}

$attachment_id = (int)$_GET['id'];
if ( !ssp_attachment_can_access( $con, $person, $attachment_id ) ) {
  http_response_code( 403 );
  exit( 'Geen toegang tot deze bijlage.' );
}

$stmt = mysqli_prepare( $con, "SELECT filename, mimetype, filesize, content FROM itsm_core_attachments WHERE id = ? AND internalonly = 0 LIMIT 1" );
mysqli_stmt_bind_param( $stmt, 'i', $attachment_id );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $filename, $mimetype, $filesize, $content );
$found = mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( !$found ) {
  http_response_code( 404 );
  exit( 'Attachment not found' );
}

$safe_filename = basename( (string)$filename );
$safe_filename = preg_replace( '/[\x00-\x1F\x7F"\\\\]/', '_', $safe_filename );
$safe_filename = $safe_filename !== '' ? $safe_filename : 'attachment';
$mimetype = preg_match( '#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', (string)$mimetype )
  ? strtolower( (string)$mimetype )
  : 'application/octet-stream';
$inline_mimetypes = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
$is_inline_image = isset( $_GET['inline'] ) && in_array( strtolower( (string)$mimetype ), $inline_mimetypes, true );
header( 'Content-Type: ' . ( $mimetype ?: 'application/octet-stream' ) );
header( 'Content-Length: ' . (int)$filesize );
header( 'Content-Disposition: ' . ( $is_inline_image ? 'inline' : 'attachment' ) . '; filename="' . $safe_filename . '"' );
header( 'X-Content-Type-Options: nosniff' );
header( "Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:" );
echo $content;
exit;
