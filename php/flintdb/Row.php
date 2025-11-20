<?php

namespace FlintDB;

use ArrayAccess,
    DomainException,
    Generator,
    IteratorAggregate,
    LogicException,
    RuntimeException;

class Row implements ArrayAccess, IteratorAggregate {
  /**
   * The row identifier
   *
   * @var string
   */
  private string $id;
  
  /**
   * In-memory columns
   *
   * @var array
   */
  private array $custom = array();
  
  /**
   * The names of the row columns
   *
   * @var array|null
   */
  private ?array $schema = null;
  
  /**
   * The name of the parent table
   *
   * @var string
   */
  private string $table;
  
  /**
   * The parent table defined schema
   *
   * @var array
   */
  private array $table_schema;
  
  /**
   * The parent table data encryption key
   *
   * @var string
   */
  private string $dek;
  
  /**
   * The name of the parent database
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
   * @param string $id
   * @param string $table
   * @param array $table_schema
   * @param string $dek
   * @param string $database
   * @param string $storage
   * @param string $kek
   * @throws DomainException
   * @throws RuntimeException
   */
  public function __construct( string $id, string &$table, array &$table_schema, string &$dek, string &$database, string &$storage, #[\SensitiveParameter] string &$kek ) {
    $this->id = $id;
    $this->table =& $table;
    $this->table_schema =& $table_schema;
    $this->dek =& $dek;
    $this->database =& $database;
    $this->storage =& $storage;
    $this->kek =& $kek;
    
    if ( ! ctype_alnum( $id ) ) {
      throw new DomainException( 'Row name should contain only alphabets and numbers' );
    }
    
    elseif ( ! is_file( $this->file() ) ) {
      throw new RuntimeException( 'Row does not exist' );
    }
  }
  
  /**
   * Returns row identifier
   *
   * @return string
   */
  public function &id(): string {
    return $this->id;
  }
  
  /**
   * Returns row file path
   *
   * @return string
   */
  public function file(): string {
    return Filesystem::join( $this->storage, $this->database, $this->table, $this->id . '.ndjson' );
  }
  
  /**
   * Returns row metadata
   *
   * @return array
   */
  public function metadata(): array {
    $file = $this->file();
    return array(
      'size' => filesize( $file ),
      'modified' => filemtime( $file ),
      'database' => $this->database,
      'table' => $this->table,
      'id' => $this->id,
      'file' => $file
    );
  }
  
  /**
   * Deletes row file
   *
   * @return bool
   */
  public function delete(): bool {
    return unlink( $this->file() );
  }
  
  /**
   * Updates columns
   *
   * @param array $columns
   * @return bool
   */
  public function update( array $columns ): bool {
    $columns[ '_id' ] = $this->id;
    $table = new Table( $this->table, $this->database, $this->storage, $this->kek );
    return $table->insert( $columns );
  }
  
  /**
   * Returns row columns name
   *
   * @return array
   */
  public function &schema(): array {
    if ( is_null( $this->schema ) ) {
      $this->schema = Filesystem::readline( $this->file(), 0, json_decode: true );
    }
    
    return $this->schema;
  }
  
  /**
   * Returns column value
   *
   * @param string $column
   * @return mixed
   * @throws LogicException
   */
  public function column( string $column ): mixed {
    if ( '_id' === $column ) {
      return $this->id;
    }
    
    $index = array_search( $column, $this->schema() );
    if ( false === $index ) {
      return $this->custom[ $column ] ?? null;
    }
    
    $value = $this->index( $index + 1 );
    $schema = new Schema( $this->table_schema );
    $options = $schema->get( $column );
    
    if ( $options && $options[ 'encrypted' ] ) {
      if ( empty( $this->kek ) ) {
        throw new LogicException( 'KEK is required to retrieve encrypted columns' );
      }
      
      $dek = Crypto::decrypt( $this->dek, $this->kek );
      if ( ! $dek ) {
        throw new LogicException( 'Invalid KEK provided' );
      }
      
      $value = Crypto::decrypt( $value, $dek );
    }
    
    return $value;
  }
  
  /**
   * Returns all columns
   *
   * @return array
   */
  public function columns(): array {
    return iterator_to_array( $this->yield_columns() );
  }
  
  /**
   * Yield all columns
   *
   * @return Generator
   * @throws LogicException
   */
  public function yield_columns(): Generator {
    $schema = new Schema( $this->table_schema );
    $values = Filesystem::readlines( $this->file(), json_decode: true );
    $keys = $this->schema();
    unset( $values[0] );
    
    if ( $schema->has_encrypted_columns() ) {
      if ( empty( $this->kek ) ) {
        throw new LogicException( 'KEK is required to retrieve encrypted columns' );
      }
      
      $dek = Crypto::decrypt( $this->dek, $this->kek );
      if ( ! $dek ) {
        throw new LogicException( 'Invalid KEK provided' );
      }
    }
    
    while ( $keys && $values ) {
      $key = array_shift( $keys );
      $value = array_shift( $values );
      $options = $schema->get( $key );
      if ( $options && $options[ 'encrypted' ] ) {
        $value = Crypto::decrypt( $value, $dek );
      }
      
      yield $key => $value;
    }
    
    yield from $this->custom;
    yield '_id' => $this->id;
  }
  
  /**
   * Returns content of line from row file
   *
   * @param int $index
   * @return mixed
   */
  public function index( int $index ): mixed {
    return Filesystem::readline( $this->file(), $index, json_decode: true );
  }
  
  /**
   * Temporarily renames column
   *
   * @param string $name
   * @param string $new_name
   * @return void
   */
  public function rename_column( string $name, string $new_name ): void {
    $index = array_search( $name, $this->schema() );
    if ( false !== $index ) {
      $this->schema[ $index ] = $new_name;
    }
  }
  
  /**
   * Returns whether column exist
   *
   * @param mixed $offset
   * @return bool
   */
  public function offsetExists( mixed $offset ): bool {
    return in_array( $offset, $this->schema() ) ||
           isset( $this->custom[ $offset ] );
  }
  
  /**
   * Returns column value
   *
   * @return mixed
   */
  public function offsetGet( mixed $offset ): mixed {
    return $this->column( $offset );
  }
  
  /**
   * Sets custom column
   *
   * @param mixed $offset
   * @param mixed $value
   * @return void
   */
  public function offsetSet( mixed $offset, mixed $value ): void {
    $this->custom[ $offset ] = $value;
  }
  
  /**
   * Removes custom column
   *
   * @param mixed $offset
   * @return void
   */
  public function offsetUnset( mixed $offset ): void {
    unset( $this->custom[ $offset ] );
  }
  
  /**
   * Yields external iterator
   *
   * @return Generator
   */
  public function getIterator(): Generator {
    yield from $this->yield_columns();
  }
}
