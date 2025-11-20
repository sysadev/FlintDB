<?php

namespace FlintDB;

use DirectoryIterator,
    RecursiveDirectoryIterator,
    RecursiveIteratorIterator,
    RuntimeException,
    SplFileObject;

class Filesystem {
  /**
   * Writes text content to file
   *
   * @param string $filename
   * @param string $content
   * @return bool
   * @throws RuntimeException
   */
  public static function write( string $filename, string $content ): bool {
    $random = Crypto::random_id();
    $tmp_filename = $filename . '.wal.' . $random;
    $file = new SplFileObject( $tmp_filename, 'w' );
    if ( ! $file->flock( LOCK_EX ) ) {
      $file = null;
      throw new RuntimeException( 'Failed to acquire lock' );
    }
    
    $written = $file->fwrite( $content );
    $file->fflush();
    $file->flock( LOCK_UN );
    $file = null;
    
    if ( $written && rename( $tmp_filename, $filename ) ) {
      return true;
    }
    
    unlink( $filename . '.wal.' . $random );
    return false;
  }
  
  /**
   * Writes JSON content to file
   *
   * @param string $file
   * @param array $content
   * @return bool
   */
  public static function write_json( string $file, array $content ): bool {
    return self::write( $file, json_encode( $content ) );
  }
  
  /**
   * Returns file content
   *
   * @param string $file
   * @return string
   */
  public static function read( string $file ): string {
    $content = '';
    $file = new SplFileObject( $file, 'r' );
    while ( ! $file->eof() ) {
      $content .= $file->fgets();
    }
    
    $file = null;
    return $content;
  }
  
  /**
   * Returns content of specific line in file
   *
   * @param string $file
   * @param int $index
   * @param bool $json_decode
   * @return mixed
   */
  public static function readline( string $file, int $index, bool $json_decode = false ): mixed {
    $file = new SplFileObject( $file, 'r' );
    $file->seek( $index );
    $content = $file->fgets();
    if ( $json_decode ) {
      $content = json_decode( $content, true );
    }
    
    $file = null;
    return $content;
  }
  
  /**
   * Returns file content line-by-line
   *
   * @param string $file
   * @param bool $json_decode
   * @return array
   */
  public static function readlines( string $file, bool $json_decode = false ): array {
    $content = [];
    $file = new SplFileObject( $file, 'r' );
    while ( ! $file->eof() ) {
      $line = $file->fgets();
      if ( $json_decode ) {
        $line = json_decode( $line, true );
      }
      
      $content[] = $line;
    }
    
    $file = null;
    return $content;
  }
  
  /**
   * Returns content of JSON file
   *
   * @param string $file
   * @return mixed
   */
  public static function read_json( string $file ): mixed {
    return json_decode( self::read( $file ), true );
  }
  
  /**
   * Joins filesystem paths
   *
   * @param string[] $paths
   * @return string
   */
  public static function join( string ...$parts ): string {
    $path = '';
    foreach ( $parts as $part ) {
      $path .= DIRECTORY_SEPARATOR . trim( $part, ' /\\' . DIRECTORY_SEPARATOR );
    }
    
    return $path;
  }
  
  /**
   * Deletes directory
   *
   * @param string $folder
   * @return void
   */
  public static function rmtree( string $folder ): void {
    if ( ! is_dir( $folder ) ) {
      return;
    }
    
    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator( $folder, RecursiveDirectoryIterator::SKIP_DOTS ),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ( $iter as $finfo ) {
      if ( $finfo->isDir() ) {
        rmdir( $finfo->getRealPath() );
      } else {
        unlink( $finfo->getRealPath() );
      }
    }
    
    rmdir( $folder );
  }
}
