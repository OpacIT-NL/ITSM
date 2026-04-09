<?php

function itsm_template_extract_variables( $template ) {
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

function itsm_template_has_variables( $template ) {
  return count( itsm_template_extract_variables( $template ) ) > 0;
}

function itsm_operator_template_filter( $templates ) {
  return array_values( array_filter(
    $templates,
    function( $template ) {
      return !itsm_template_has_variables( $template );
    }
  ) );
}
