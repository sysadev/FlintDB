<?php

namespace FlintDB;

use Countable;

class Schema implements Countable {
  /**
   * Valid data types
   *
   * @var array
   */
  private static array $types = array(
    '@bool'   => 'is_bool',
    '@enum'   => 'in_array',
    '@float'  => 'is_float',
    '@int'    => 'is_integer',
    '@list'   => 'array_is_list',
    '@number' => 'is_numeric',
    '@object' => 'is_array',
    '@text'   => 'is_string'
  );
  
  /**
   * Schema container
   *
   * @var array
   */
  private array $schema;
  
  /**
   * Constructor
   *
   * @param array $schema
   */
  public function __construct( array $schema = [] ) {
    $this->schema = $schema;
  }
  
  /**
   * Adds column
   *
   * @param string $column
   * @param string $type
   * @param bool $required
   * @param bool $encrypted
   * @param mixed[] $options
   * @return self
   * @throws DomainException
   */
  public function add(
                       string $column,
                       string $type,
                       bool $required = false,
                       bool $encrypted = false,
                       mixed ...$options
                     ): self {
    if ( ! isset( self::$types[ $type ] ) ) {
      throw new DomainException( 'Unsupported type for column: ' . $column );
    }
    
    $data = array();
    $data[ 'callback' ] = self::$types[ $type ];
    switch ( $type ) {
      case '@enum':
        if (
             ! isset( $options[ 'enum_values' ] ) ||
             ! is_array( $options[ 'enum_values' ] ) ||
               count( $options[ 'enum_values' ] ) < 1
           ) {
          throw new DomainException( 'Enum values must be an array with one or more elements' );
        }
        
        $data[ 'args' ] = array();
        $data[ 'args' ][] = array_values( $data[ 'enum_values' ] );
        $data[ 'args' ][] = true;
        break;
      case '@bool':
      case '@float':
      case '@int':
      case '@list':
      case '@number':
      case '@object':
      case '@text':
        $data[ 'args' ] = array();
        break;
      default:
        $data[ 'args' ] = (array) ( $options[ 'args' ] ?? [] );
    }
    
    $data[ 'required' ] = $required;
    $data[ 'encrypted' ] = $encrypted;
    $this->schema[ $column ] = $data;
    return $this;
  }
  
  /**
   * Returns column data
   *
   * @param string $column
   * @return array|null
   */
  public function get( string $column ): ?array {
    return $this->schema[ $column ] ?? null;
  }
  
  /**
   * Returns whether data is valid for column
   *
   * @param string $column
   * @param mixed $value
   * @return bool
   */
  public function valid( string $column, mixed $value ): bool {
    try {
      $data = $this->get( $column );
      if ( ! $data ) {
        return true;
      }
      
      $callback = $data[ 'callback' ];
      if ( ! is_callable( $callback ) ) {
        return false;
      }
      
      elseif ( ! $data[ 'required' ] && is_null( $value ) ) {
        return true;
      }
      
      return ( true === $callback( $value, ...$data[ 'args' ] ) );
    } catch ( Throwable $err ) {
      return false;
    }
  }
  
  /**
   * Removes column
   *
   * @param string $column
   * @return self
   */
  public function remove( string $column ): self {
    unset( $this->schema[ $column ] );
    return $this;
  }
  
  /**
   * Checks for encrypted columns
   *
   * @return bool
   */
  public function has_encrypted_columns(): bool {
    foreach ( $this->schema as $column ) {
      if ( $column[ 'encrypted' ] ) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Returns sorted schema
   *
   * @return array
   */
  public function sorted_schema(): array {
    ksort( $this->schema );
    return $this->schema;
  }
  
  /**
   * Returns number of columns
   *
   * @return int
   */
  public function count(): int {
    return count( $this->schema );
  }
}
