<?php

spl_autoload_register(function ( $name ) {
  if ( str_starts_with( $name, 'FlintDB\\' ) ) {
    $name = str_replace( 'FlintDB\\', DIRECTORY_SEPARATOR, $name );
    $name = implode( DIRECTORY_SEPARATOR, explode( '\\', $name ) );
    $file = __DIR__ . $name . '.php';
    if ( is_file( $file ) ) {
      require_once $file;
    }
  }
});
