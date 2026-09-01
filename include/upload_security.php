<?php

function itsm_attachment_max_bytes() {
  return 10 * 1024 * 1024;
}

function itsm_attachment_allowed_types() {
  return [
    'jpg' => [ 'image/jpeg' ],
    'jpeg' => [ 'image/jpeg' ],
    'png' => [ 'image/png' ],
    'gif' => [ 'image/gif' ],
    'webp' => [ 'image/webp' ],
    'pdf' => [ 'application/pdf' ],
    'txt' => [ 'text/plain' ],
    'log' => [ 'text/plain' ],
    'csv' => [ 'text/plain', 'text/csv', 'application/csv' ],
    'rtf' => [ 'application/rtf', 'text/rtf', 'text/plain' ],
    'doc' => [ 'application/msword', 'application/x-ole-storage', 'application/vnd.ms-office' ],
    'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ],
    'xls' => [ 'application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office' ],
    'xlsx' => [ 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ],
    'ppt' => [ 'application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/vnd.ms-office' ],
    'pptx' => [ 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip' ],
    'odt' => [ 'application/vnd.oasis.opendocument.text', 'application/zip' ],
    'ods' => [ 'application/vnd.oasis.opendocument.spreadsheet', 'application/zip' ],
    'odp' => [ 'application/vnd.oasis.opendocument.presentation', 'application/zip' ],
    'zip' => [ 'application/zip', 'application/x-zip-compressed' ],
    '7z' => [ 'application/x-7z-compressed' ],
    'gz' => [ 'application/gzip', 'application/x-gzip' ],
    'tar' => [ 'application/x-tar' ],
    'eml' => [ 'message/rfc822', 'text/plain' ],
    'msg' => [ 'application/vnd.ms-outlook', 'application/x-ole-storage' ]
  ];
}

function itsm_attachment_archive_matches_extension( $extension, $content ) {
  $required_markers = [
    'docx' => [ '[Content_Types].xml', 'word/' ],
    'xlsx' => [ '[Content_Types].xml', 'xl/' ],
    'pptx' => [ '[Content_Types].xml', 'ppt/' ],
    'odt' => [ 'application/vnd.oasis.opendocument.text' ],
    'ods' => [ 'application/vnd.oasis.opendocument.spreadsheet' ],
    'odp' => [ 'application/vnd.oasis.opendocument.presentation' ]
  ];
  if ( !isset( $required_markers[$extension] ) ) {
    return true;
  }

  foreach ( $required_markers[$extension] as $marker ) {
    if ( strpos( $content, $marker ) === false ) {
      return false;
    }
  }
  return true;
}

function itsm_attachment_detect_mimetype( $content ) {
  if ( !class_exists( 'finfo' ) ) {
    return '';
  }
  $finfo = new finfo( FILEINFO_MIME_TYPE );
  $detected = $finfo->buffer( (string)$content );
  return $detected !== false ? strtolower( trim( (string)$detected ) ) : '';
}

function itsm_validate_attachment_content( $filename, $content ) {
  $filename = basename( str_replace( "\0", '', (string)$filename ) );
  $content = (string)$content;
  $filesize = strlen( $content );

  if ( $filename === '' || $filesize <= 0 ) {
    return [ 'ok' => false, 'error' => 'De bijlage is leeg of heeft geen geldige bestandsnaam.', 'mime' => '' ];
  }
  if ( $filesize > itsm_attachment_max_bytes() ) {
    return [ 'ok' => false, 'error' => 'Bijlage is te groot. Maximaal 10 MB.', 'mime' => '' ];
  }

  $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
  $allowed_types = itsm_attachment_allowed_types();
  if ( $extension === '' || !isset( $allowed_types[$extension] ) ) {
    return [ 'ok' => false, 'error' => 'Dit bestandstype is niet toegestaan.', 'mime' => '' ];
  }

  $detected_mime = itsm_attachment_detect_mimetype( $content );
  if ( $detected_mime === '' ) {
    return [ 'ok' => false, 'error' => 'Het bestandstype kon niet veilig worden vastgesteld.', 'mime' => '' ];
  }
  if ( !in_array( $detected_mime, $allowed_types[$extension], true ) ) {
    return [ 'ok' => false, 'error' => 'De bestandsinhoud komt niet overeen met de bestandsextensie.', 'mime' => $detected_mime ];
  }
  if ( !itsm_attachment_archive_matches_extension( $extension, $content ) ) {
    return [ 'ok' => false, 'error' => 'De archiefinhoud komt niet overeen met het opgegeven documenttype.', 'mime' => $detected_mime ];
  }

  return [ 'ok' => true, 'error' => '', 'mime' => $detected_mime ];
}

function itsm_uploaded_attachment_validation( $file ) {
  if ( !is_array( $file ) || (int)( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
    return [ 'ok' => false, 'error' => 'Bijlage uploaden mislukt.', 'mime' => '' ];
  }
  if ( empty( $file['tmp_name'] ) || !is_uploaded_file( $file['tmp_name'] ) ) {
    return [ 'ok' => false, 'error' => 'De upload kon niet worden geverifieerd.', 'mime' => '' ];
  }
  if ( (int)( $file['size'] ?? 0 ) > itsm_attachment_max_bytes() ) {
    return [ 'ok' => false, 'error' => 'Bijlage is te groot. Maximaal 10 MB.', 'mime' => '' ];
  }

  $content = file_get_contents( $file['tmp_name'] );
  if ( $content === false ) {
    return [ 'ok' => false, 'error' => 'De bijlage kon niet worden gelezen.', 'mime' => '' ];
  }

  $validation = itsm_validate_attachment_content( $file['name'] ?? '', $content );
  $validation['content'] = $content;
  return $validation;
}

function itsm_attachment_mimetype_can_inline( $mimetype ) {
  return in_array( strtolower( (string)$mimetype ), [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true );
}

?>
