<?php

namespace FlintDB;

use DirectoryIterator,
    DomainException,
    Generator,
    LogicException,
    RuntimeException;

class Table {
  /**
   * The name of the table
   *
   * @var string
   */
  private string $name;
  
  /**
   * The table defined schema
   *
   * @var array|null
   */
  private ?array $schema = null;
  
  /**
   * The table data encryption key
   *
   * @var string|null
   */
  private ?string $dek = null;
  
  /**
   * The parent database name
   *
   * @var string
   */
  private string $database;
  
  /**
   * The parent database directory
   *
   * @var string
   */
  private string $storage;
  
  /**
   * The parent database encryption key
   *
   * @var string
   */
  private string $kek;
  
  /**
   * Constructor
   *
   * @param string $name
   * @param string $database
   * @param string $storage
   * @param string $kek
   * @throws DomainException
   * @throws RuntimeException
   */
  public function __construct( string $name, string &$database, string &$storage, #[\SensitiveParameter] string &$kek ) {
    $this->name = $name;
    $this->database =& $database;
    $this->storage =& $storage;
    $this->kek =& $kek;
    
    if ( ! ctype_alnum( $this->name ) ) {
      throw new DomainException( 'Table name should contain only alphabets and numbers' );
    }
    
    elseif ( ! is_dir( $this->folder() ) ) {
      throw new RuntimeException( 'Table does not exist' );
    }
  }
  
  /**
   * Returns table name
   *
   * @return string
   */
  public function &name(): string {
    return $this->name;
  }
  
  /**
   * Renames table
   *
   * @param string $new_name
   * @return string
   * @throws DomainException
   */
  private function rename(): bool {
    if ( ! ctype_alnum( $new_name ) ) {
      throw new DomainException( 'Table name should contain only alphabets and numbers' );
    }
    
    $target = Filesystem::join( $this->storage, $this->database, $this->name );
    return rename( $this->folder(), $target );
  }
  
  /**
   * Returns table directory path
   *
   * @return string
   */
  public function folder(): string {
    return Filesystem::join( $this->storage, $this->database, $this->name );
  }
  
  /**
   * Returns table metadata
   *
   * @param bool $excess
   * @return array
   */
  public function metadata( bool $excess = false ): array {
    $folder = $this->folder();
    $file = Filesystem::join( $folder, '.metadata' );
    $metadata = Filesystem::read_json( $file );
    $metadata[ 'modified' ] = filemtime( $folder );
    $metadata[ 'rows' ] = $metadata[ 'size' ] = 0;
    $metadata[ 'database' ] = $this->database;
    $metadata[ 'name' ] = $this->name;
    
    $this->dek = $metadata[ 'dek' ];
    $this->schema = $metadata[ 'schema' ];
    
    if ( ! $excess ) {
      return $metadata;
    }
    
    foreach ( $this->rows() as $row ) {
      $metadata[ 'size' ] += $row->metadata()[ 'size' ];
      $metadata[ 'rows' ]++;
    }
    
    return $metadata;
  }
  
  /**
   * Deletes table directory
   *
   * @return bool
   */
  public function delete(): bool {
    $target = Filesystem::join( $this->storage, $this->database, '.deleted_' . $this->name );
    if ( ! rename( $this->folder(), $target ) ) {
      return false;
    }
    
    Filesystem::rmtree( $target );
    $cache = new Cache( $this );
    $cache->flush();
    return true;
  }
  
  /**
   * Returns table Data Encryption Key (DEK)
   *
   * @return string
   */
  public function &dek(): string {
    if ( is_null( $this->dek ) ) {
      $this->metadata();
    }
    
    return $this->dek;
  }
  
  /**
   * Returns table schema
   *
   * @return array
   */
  public function &schema(): array {
    if ( is_null( $this->schema ) ) {
      $this->metadata();
    }
    
    return $this->schema;
  }
  
  /**
   * Modifies table schema
   *
   * @param callable $callback
   * @return bool
   * @throws LogicException
   */
  public function alter( callable $callback ): bool {
    try {
      $schema = $callback( new Schema );
    } catch ( Throwable $err ) {
      $schema = new Schema;
    }
    
    if ( ! $schema instanceof Schema ) {
      throw new LogicException( 'Invalid schema provided' );
    }
    
    $schema->remove( '_id' );
    
    $metadata = array();
    $metadata[ 'schema' ] = $schema->sorted_schema();
    $file = Filesystem::join( $this->folder(), '.metadata' );
    return Filesystem::write_json( $file, $metadata );
  }
  
  /**
   * Inserts row to the table
   *
   * @param array $columns
   * @return bool
   * @throws RuntimeException
   * @throws DomainException
   * @throws LogicException
   */
  public function insert( array $columns ): bool {
    $folder = $this->folder();
    if ( isset( $columns[ '_id' ] ) ) {
      try {
        $this->row( $columns[ '_id' ] );
      } catch ( RuntimeException $err ) {
        throw new RuntimeException( 'ID does not match any row' );
      }
    } else {
      $id = Crypto::random_id(8);
      while ( is_file( $folder . '/' . $id . '.ndjson' ) ) {
        $id = Crypto::random_id(8);
      }
      
      $columns[ '_id' ] = $id;
    }
    
    $metadata = $this->metadata();
    $file = Filesystem::join( $folder, $columns[ '_id' ] . '.ndjson' );
    if ( is_file( $file ) ) {
      $row = $this->row( $columns[ '_id' ] );
      foreach ( $row->yield_columns() as $column => $value ) {
        if ( ! isset( $columns[ $column ] ) ) {
          $columns[ $column ] = $value;
        }
      }
    } else {
      foreach ( $metadata[ 'schema' ] as $column => $options ) {
        if ( ! isset( $columns[ $column ] ) ) {
          $columns[ $column ] = null;
        }
      }
    }
    
    $schema = new Schema( $metadata[ 'schema' ] );
    foreach ( $columns as $column => $value ) {
      if ( ! $schema->valid( $column, $value ) ) {
        throw new DomainException( 'Invalid data type for column: ' . $column );
      }
    }
    
    unset( $columns[ '_id' ] );
    ksort( $columns );
    
    $data = json_encode( array_keys( $columns ) ) . PHP_EOL;
    if ( $schema->has_encrypted_columns() ) {
      if ( empty( $this->kek ) ) {
        throw new LogicException( 'KEK required to encrypt columns' );
      }
      
      $dek = Crypto::decrypt( $metadata[ 'dek' ], $this->kek );
      if ( ! $dek ) {
        throw new LogicException( 'Invalid KEK provided' );
      }
      
      foreach ( $columns as $column => $value ) {
        $column = $schema->get( $column );
        if ( $column[ 'encrypted' ] ) {
          $value = Crypto::encrypt( $value, $dek );
        }
        
        $data .= json_encode( $value ) . PHP_EOL;
      }
    }
    
    else {
      foreach ( $columns as $column => $value ) {
        $data .= json_encode( $value ) . PHP_EOL;
      }
    }
    
    $written = Filesystem::write( $file, $data );
    if ( $written ) {
      $cache = new Cache( $this );
      $cache->flush();
    }
    
    return $written;
  }
  
  /**
   * Insert many rows to the table
   *
   * @param array[] $many_columns
   * @return array
   */
  public function insert_many( array ...$many_columns ): array {
    $status = array();
    foreach ( $many_columns as $index => $columns ) {
      $status[ $index ] = $this->insert( $columns );
    }
    
    return $status;
  }
  
  /**
   * Returns instance of Row class
   *
   * @param string $id
   * @return Row
   */
  public function row( string $id ): Row {
    return new Row( $id, $this->name, $this->schema(), $this->dek(), $this->database, $this->storage, $this->kek );
  }
  
  /**
   * Yields all rows
   *
   * @param array $exclude
   * @return Generator
   */
  public function rows( array $exclude = [] ): Generator {
    foreach ( new DirectoryIterator( $this->folder() ) as $row ) {
      if ( str_ends_with( (string) $row, '.ndjson' ) ) {
        $row = $row->getBasename( '.ndjson' );
        if ( ! in_array( $row, $exclude ) ) {
          yield $this->row( $row );
        }
      }
    }
  }
  
  /**
   * Returns instance of Query class
   *
   * @return Query
   */
  public function query(): Query {
    $database = new Database( $this->database, $this->storage, $this->kek );
    return ( new Query( $database ) )->from( $this->name );
  }
  
  /**
   * Returns matching rows from the table
   *
   * @param array $criteria
   * @return Collection
   */
  public function find( array $criteria ): Collection {
    $query = $this->query();
    foreach ( $criteria as $key => $value ) {
      $query->where( $key, '=', $value );
    }
    
    $query->no_cache();
    return $query->fetch();
  }
  
  /**
   * Returns matching rows from the table
   *
   * @param string $id
   * @return ?Row
   */
  public function find_one( array $criteria ): ?Row {
    $query = $this->query();
    foreach ( $criteria as $key => $value ) {
      $query->where( $key, '=', $value );
    }
    
    $query->limit(1);
    $query->no_cache();
    $data = $query->fetch();
    return $data->item(0);
  }
}
