<?php

function itsm_ensure_api_token_table( $con ) {
  mysqli_query( $con, "
    CREATE TABLE IF NOT EXISTS itsm_api_tokens (
      id int(11) NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      token_prefix varchar(32) NOT NULL,
      token_hash varchar(255) NOT NULL,
      operatorid int(11) NOT NULL,
      createdby int(11) DEFAULT NULL,
      createdat datetime NOT NULL DEFAULT current_timestamp(),
      lastusedat datetime DEFAULT NULL,
      expiresat datetime DEFAULT NULL,
      active int(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (id),
      KEY token_prefix (token_prefix),
      KEY operatorid (operatorid),
      KEY createdby (createdby),
      KEY active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
  " );
}

function itsm_generate_api_token() {
  return 'itsm_' . bin2hex( random_bytes( 32 ) );
}

function itsm_api_token_prefix( $token ) {
  return substr( $token, 0, 16 );
}

function itsm_hash_api_token( $token ) {
  return password_hash( $token, PASSWORD_DEFAULT );
}

?>
