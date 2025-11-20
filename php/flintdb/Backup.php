<?php

namespace FlintDB;

use RuntimeException,
    ZipArchive;

class Backup {
  /**
   * Backup database to zip file
   *
   * @param Database $database
   * @param string $file
   * @return void
   * @throws RuntimeException
   */
  public function dump( Database $database, string $file ): void {
    $zip = new ZipArchive;
    if ( $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
      throw new RuntimeException( 'Failed to open the ZIP file' );
    }
    
    $dir = $database->storage();
    $zip->addEmptyDir( $database->name() );
    $zip->addFile( $dir . '/' . $database->name() . '/.metadata', $database->name() . '/.metadata' );
    foreach ( $this->database->yield_tables() as $table ) {
      $folder = $database->name() . '/' . $table->name();
      $zip->addEmptyDir( $folder );
      $zip->addFile( $dir . '/' . $folder . '/.metadata', $folder . '/.metadata' );
      foreach ( $table->yield_rows() as $row ) {
        $file = $folder . '/' . $row->id() . '.ndjson';
        $zip->addFile( $dir . $file, $file );
      }
    }
    
    $zip->close();
  }
  
  /**
   * Recover database from zip file
   *
   * @param string $file
   * @param string $storage
   * @return void
   * @throws RuntimeException
   */
  public static function load( string $file, string $storage ): void {
    $zip = new ZipArchive;
    if ( $zip->open( $file ) !== true ) {
      throw new RuntimeException( 'Failed to open the ZIP file' );
    }
    
    $zip->extractTo( $storage );
    $zip->close();
  }
}
