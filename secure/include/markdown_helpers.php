<?php

function markdown_inline_render( $text ) {
  $placeholders = [];
  $placeholder_index = 0;

  $text = preg_replace_callback( '/`([^`\r\n]+)`/', function ( $matches ) use ( &$placeholders, &$placeholder_index ) {
    $token = '__MDCODE' . $placeholder_index++ . '__';
    $placeholders[ $token ] = '<code>' . htmlspecialchars( $matches[1] ) . '</code>';
    return $token;
  }, $text );

  $text = preg_replace_callback( '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', function ( $matches ) {
    $label = htmlspecialchars( $matches[1] );
    $url = htmlspecialchars( $matches[2] );
    return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
  }, $text );

  $patterns = [
    '/\|\|(.+?)\|\|/' => '<span class="kb-spoiler">$1</span>',
    '/~~(.+?)~~/' => '<del>$1</del>',
    '/\*\*(.+?)\*\*/' => '<strong>$1</strong>',
    '/__(.+?)__/' => '<u>$1</u>',
    '/\*(.+?)\*/' => '<em>$1</em>',
    '/_(.+?)_/' => '<em>$1</em>'
  ];

  foreach ( $patterns as $pattern => $replacement ) {
    $text = preg_replace( $pattern, $replacement, $text );
  }

  if ( !empty( $placeholders ) ) {
    $text = strtr( $text, $placeholders );
  }

  return $text;
}

function markdown_to_html( $markdown ) {
  $markdown = str_replace( [ "\r\n", "\r" ], "\n", (string)$markdown );
  $markdown = htmlspecialchars( $markdown, ENT_QUOTES, 'UTF-8' );

  $code_blocks = [];
  $markdown = preg_replace_callback( '/```(.*?)```/s', function ( $matches ) use ( &$code_blocks ) {
    $token = '__CODEBLOCK_' . count( $code_blocks ) . '__';
    $code_blocks[ $token ] = '<pre><code>' . trim( $matches[1], "\n" ) . '</code></pre>';
    return $token;
  }, $markdown );

  $lines = explode( "\n", $markdown );
  $html = '';
  $in_ul = false;
  $in_ol = false;
  $in_quote = false;

  $close_lists = function () use ( &$html, &$in_ul, &$in_ol ) {
    if ( $in_ul ) {
      $html .= "</ul>\n";
      $in_ul = false;
    }
    if ( $in_ol ) {
      $html .= "</ol>\n";
      $in_ol = false;
    }
  };

  $close_quote = function () use ( &$html, &$in_quote ) {
    if ( $in_quote ) {
      $html .= "</blockquote>\n";
      $in_quote = false;
    }
  };

  foreach ( $lines as $line ) {
    $trimmed = trim( $line );

    if ( $trimmed === '' ) {
      $close_lists();
      $close_quote();
      continue;
    }

    if ( isset( $code_blocks[ $trimmed ] ) ) {
      $close_lists();
      $close_quote();
      $html .= $code_blocks[ $trimmed ] . "\n";
      continue;
    }

    if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $matches ) ) {
      $close_lists();
      $close_quote();
      $level = min( 6, strlen( $matches[1] ) );
      $html .= '<h' . $level . '>' . markdown_inline_render( $matches[2] ) . '</h' . $level . ">\n";
      continue;
    }

    if ( preg_match( '/^>\s?(.*)$/', $trimmed, $matches ) ) {
      $close_lists();
      if ( !$in_quote ) {
        $html .= "<blockquote>\n";
        $in_quote = true;
      }
      $html .= '<p>' . markdown_inline_render( $matches[1] ) . "</p>\n";
      continue;
    }

    $close_quote();

    if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $matches ) ) {
      if ( !$in_ul ) {
        $close_lists();
        $html .= "<ul>\n";
        $in_ul = true;
      }
      $html .= '<li>' . markdown_inline_render( $matches[1] ) . "</li>\n";
      continue;
    }

    if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $matches ) ) {
      if ( !$in_ol ) {
        $close_lists();
        $html .= "<ol>\n";
        $in_ol = true;
      }
      $html .= '<li>' . markdown_inline_render( $matches[1] ) . "</li>\n";
      continue;
    }

    $close_lists();
    $html .= '<p>' . markdown_inline_render( $trimmed ) . "</p>\n";
  }

  $close_lists();
  $close_quote();

  if ( !empty( $code_blocks ) ) {
    $html = strtr( $html, $code_blocks );
  }

  return $html;
}
