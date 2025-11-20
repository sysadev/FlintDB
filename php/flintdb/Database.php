<?php

namespace FlintDB;

use DirectoryIterator,
    DomainException,
    Generator,
    LogicException,
    RuntimeException;

class Database {
  /**
   * The name of the database
   *
   * @var string
   */
  private string $name;
  
  /**
   * The filesystem path to the database parent directory
   *
   * @var string
   */
  private string $storage;
  
  /**
   * The encryption key used for Transparent Data Encryption (TDE)
   *
   * @var string
   */
  private string $kek;
  
  /**
   * Constructor
   *
   * @param string $name
   * @param ?string $storage
   * @param string $kek
   * @throws DomainException
   * @throws RuntimeException
   */
  public function __construct( string $name, ?string $storage = null, #[\SensitiveParameter] string $kek = '' ) {
    $this->name = $name;
    $this->storage = realpath( $storage ?? getcwd() );
    $this->kek = $kek;
    
    if ( ! ctype_alnum( $this->name ) ) {
      throw new DomainException( 'Database name should contain only alphabets and numbers' );
    }
    
    $folder = $this->folder();
    if ( is_dir( $folder ) ) {
      return;
    }
    
    elseif ( ! mkdir( $folder ) ) {
      throw new RuntimeException( 'Failed to create database directory' );
    }
    
    $metadata = array();
    $metadata[ 'created' ] = time();
    $metadata[ 'version' ] = Version::SEMVER;
    $file = Filesystem::join( $folder, '.metadata' );
    if ( ! Filesystem::write_json( $file, $metadata ) ) {
      throw new RuntimeException( 'Failed to create database metadata' );
    }
  }
  
  /**
   * Returns database name
   *
   * @return string
   */
  public function &name(): string {
    return $this->name;
  }
  
  /**
   * Renames database
   *
   * @param string $new_name
   * @return bool
   * @throws DomainException
   */
  public function rename( string $new_name ): bool {
    if ( ! ctype_alnum( $new_name ) ) {
      throw new DomainException( 'Database name should contain only alphabets and numbers' );
    }
    
    $target = Filesystem::join( $this->storage, $new_name );
    if ( is_dir( $target ) ) {
      return false;
    }
    
    return rename( $this->folder(), $target );
  }
  
  /**
   * Returns database directory path
   *
   * @return string
   */
  public function folder(): string {
    return Filesystem::join( $this->storage, $this->name );
  }
  
  /**
   * Returns database metadata
   *
   * @param bool $excess
   * @return array
   */
  public function metadata( bool $excess = false ): array {
    $folder = $this->folder();
    $file = Filesystem::join( $folder, '.metadata' );
    $metadata = Filesystem::read_json( $file );
    $metadata[ 'modified' ] = filemtime( $folder );
    $metadata[ 'tables' ] = $metadata[ 'size' ] = 0;
    if ( ! $excess ) {
      return $metadata;
    }
    
    foreach ( $this->tables() as $table ) {
      $metadata[ 'size' ] += $table->metadata()[ 'size' ];
      $metadata[ 'tables' ]++;
    }
    
    return $metadata;
  }
  
  /**
   * Deletes database directory
   *
   * @return bool
   */
  public function delete(): bool {
    $target = Filesystem::join( $this->storage, '.deleted_' . $this->name );
    if ( ! rename( $this->folder(), $target ) ) {
      return false;
    }
    
    Filesystem::rmtree( $target );
    return true;
  }
  
  /**
   * Creates new table
   *
   * @param string $name
   * @param callable|null $callback
   * @return bool
   * @throws DomainException
   * @throws LogicException
   * @throws RuntimeException
   */
  public function create_table( string $name, ?callable $callback = null ): bool {
    if ( ! ctype_alnum( $name ) ) {
      throw new DomainException( 'Table name should contain only alphabets and numbers' );
    }
    
    $folder = Filesystem::join( $this->folder(), $name );
    if ( is_dir( $folder ) ) {
      return false;
    }
    
    try {
      $callback = $callback ?? fn ( $x ) => $x;
      $schema = $callback( new Schema );
    } catch ( Throwable $err ) {
      $schema = new Schema;
    }
    
    if ( ! $schema instanceof Schema ) {
      throw new LogicException( 'Invalid schema provided' );
    }
    
    $has_encrypted_columns = $schema->has_encrypted_columns();
    if ( $has_encrypted_columns && empty( $this->kek ) ) {
      throw new LogicException( 'KEK is required to encrypt columns' );
    }
    
    elseif ( ! mkdir( $folder ) ) {
      throw new RuntimeException( 'Failed to create table directory' );
    }
    
    $schema->remove( '_id' );
    
    $metadata = array();
    $metadata[ 'created' ] = time();
    $metadata[ 'schema' ] = $schema->sorted_schema();
    $metadata[ 'dek' ] = '';
    
    if ( $has_encrypted_columns ) {
      $metadata[ 'dek' ] = Crypto::random_dek( $this->kek );
    }
    
    $file = Filesystem::join( $folder, '.metadata' );
    if ( ! Filesystem::write_json( $file, $metadata ) ) {
      $this->delete();
      throw new RuntimeException( 'Failed to create table metadata' );
    }
    
    return true;
  }
  
  /**
   * Returns an instance of specific table within the database
   *
   * @param string $name
   * @return Table
   */
  public function table( string $name ): Table {
    return new Table( $name, $this->name, $this->storage, $this->kek );
  }
  
  /**
   * Yields instance Table class for all tables within the database
   *
   * @param array $exclude
   * @return Generator
   */
  public function tables( array $exclude = [] ): Generator {
    foreach ( new DirectoryIterator( $this->folder() ) as $table ) {
      if (
           ctype_alnum( (string) $table ) &&
           ! in_array( (string) $table, $exclude )
         ) {
          yield $this->table( (string) $table );
      }
    }
  }
  
  /**
   * Returns query instance for the given table
   *
   * @param string $table
   * @return Query
   */
  public function query( string $table ): Query {
    return ( new Query( $this ) )->from( $table );
  }
}
