<?php

namespace FlintDB;

use DomainException,
    LogicException,
    ReflectionFunction,
    ReflectionMethod;

class Query {
  /**
   * The query content
   * @var array
   */
  private array $query = array();
  
  /**
   * To allow caching query result
   * @var bool
   */
  private bool $cache = true;
  
  /**
   * To detect whether query has been normalized
   * @var bool
   */
  private bool $ready = false;
  
  /**
   * Table instance to query data from
   * @var Table|null
   */
  private ?Table $table = null;
  
  /**
   * Parent Database instance of table
   * @var Database
   */
  private Database $database;
  
  /**
   * To slice query result
   * @var array
   */
  private array $limit = [ 0, -1 ];
  
  /**
   * Constructor
   * @param Database $database
   */
  public function __construct( Database $database ) {
    $this->database = $database;
  }
  
  /**
   * Sets the table to query data from
   * @param string $table
   * @return self
   */
  public function from( string $table ): self {
    $this->table = $this->database->table( $table );
    return $this;
  }
  
  /**
   * Performs left outer join
   * @param string $table
   * @param array $on
   * @param string|null $prefix
   * @return self
   */
  public function join( string $table, array $on, ?string $prefix = null ): self {
    $prefix = $prefix ?? $table . '.';
    $this->query[ 'join' ][ $table ] = [ $on, $prefix ];
    return $this;
  }
  
  /**
   * Maps callback to rows
   * @param array|string $callback
   * @return self
   */
  public function map( array | string $callback ): self {
    $reflection = is_array( $callback )
                    ? new ReflectionMethod( ...$callback )
                    : new ReflectionFunction( $callback );
    $this->query[ 'map' ][] = [ $callback, (string) $reflection ];
    return $this;
  }
  
  /**
   * Conditional filtering
   * @param string $column
   * @param string $operator
   * @param mixed $value
   * @return self
   */
  public function where( string $column, string $operator, mixed $value ): self {
    $this->query[ 'where' ][ $column ] = [ $operator, $value ];
    return $this;
  }
  
  /**
   * Renames column
   * @param string $column
   * @param string $new_name
   * @return self
   */
  public function select( string $column, string $new_name ): self {
    $this->query[ 'select' ][ $column ] = $new_name;
    return $this;
  }
  
  /**
   * Retrieves distinct, non repeated values
   * @param string $column
   * @return self
   */
  public function distinct( string $column ): self {
    $this->query[ 'distinct' ][] = $column;
    return $this;
  }
  
  /**
   * Sort rows base on column's value
   * @param string $column
   * @param string $order
   * @return self
   * @throws DomainException
   */
  public function sort( string $column, string $order = 'ASC' ): self {
    $order = strtoupper( $order );
    if ( 'ASC' !== $order && 'DESC' !== $order ) {
      throw new DomainException( 'Order must be either "ASC" or "DESC"' );
    }
    
    $this->query[ 'sort' ][ $column ] = $order === 'ASC' ? 1 : -1;
    return $this;
  }
  
  /**
   * Conditional filtering by mapping callback
   * @param array|string $callback
   * @return self
   */
  public function filter( array | string $callback ): self {
    $reflection = is_array( $callback )
                    ? new ReflectionMethod( ...$callback )
                    : new ReflectionFunction( $callback );
    $this->query[ 'filter' ][] = [ $callback, (string) $reflection ];
    return $this;
  }
  
  /**
   * Restrict the number of rows returned
   * @param int $max
   * @param int $offset
   * @return self
   * @throws DomainException
   */
  public function limit( int $max, int $offset = 0 ): self {
    if ( $max < 1 ) {
      throw new DomainException( 'Maximum limit must be greater than zero' );
    }
    
    $this->limit = [ $offset, $max ];
    return $this;
  }
  
  /**
   * Disable query result caching
   * @return self
   */
  public function no_cache(): self {
    $this->cache = false;
    return $this;
  }
  
  /**
   * Returns query result
   * @param int|null $cache_lifetime
   * @return Collection
   */
  public function fetch( ?int $cache_lifetime = null ): Collection {
    $this->normalize();
    if ( $this->cache ) {
      $cache = new Cache( ...[ ...$this->identifier(), $cache_lifetime ] );
      if ( $cache->valid() ) {
        return ( new Collection( $cache->get() ) )->limit( ...$this->limit );
      }
    }
    
    $data = array();
    foreach ( $this->table->rows() as $index => $row ) {
      if ( ! empty( $this->query[ 'join' ] ) ) {
        $join_object = new Join( $row );
        foreach ( $this->query[ 'join' ] as $table => $options ) {
          $join_object->table( $this->database->table( $table ) );
          $join_object->options( $options[0] );
          $join_row = $join_object->row();
          if ( $join_row ) {
            foreach ( $join_row->yield_columns() as $column => $value ) {
              $row[ $options[1] . $column ] = $value;
            }
          }
        }
      }
      
      foreach ( $this->query[ 'map' ] as $map ) {
        $map[0]( $row );
      }
      
      if ( ! empty( $this->query[ 'where' ] ) ) {
        $where_object = new Where( $row );
        foreach ( $this->query[ 'where' ] as $column => $criteria ) {
          $where_object->criteria( $column, ...$criteria );
          if ( $where_object->match() ) {
            $data[ $index ] = $row;
          }
        }
      } else {
        $data[ $index ] = $row;
      }
      
      if ( ! isset( $data[ $index ] ) ) {
        continue;
      }
      
      foreach ( $this->query[ 'select' ] as $column => $new_name ) {
        $row->rename_column( $column, $new_name );
      }
    }
    
    foreach ( $this->query[ 'distinct' ] as $column ) {
      $values = [];
      foreach ( $data as $index => $row ) {
        $value = $row[ $column ];
        if ( in_array( $value, $values, true ) ) {
          unset( $data[ $index ] );
        } else {
          $values[] = $value;
        }
      }
    }
    
    if ( ! empty( $this->query[ 'sort' ] ) ) {
      usort( $data, function ( $a, $b ) {
        foreach ( $this->query[ 'sort' ] as $column => $order ) {
          $cmp = match ( gettype( $a[ $column ] ) ) {
            'boolean' => (int) $a[ $column ] - (int) $b[ $column ],
            'double', 'integer' => ( $a[ $column ] > $b[ $column ] ) - ( $a[ $column ] < $b[ $column ] ),
            'string' => strcmp( $a[ $column ], $b[ $column ] ),
            default => $a[ $column ] <=> $b[ $column ]
          };
          
          if ( $cmp !== 0 ) {
            return $cmp * $order;
          }
        }
        
        return 0;
      });
    }
    
    foreach ( $this->query[ 'filter' ] as $filter ) {
      foreach ( $data as $index => $row ) {
        if ( ! $filter[0]( $row ) ) {
          unset( $data[ $index ] );
        }
      }
    }
    
    $data = array_values( $data );
    if ( $this->cache ) {
      $cache->put( $data );
    }
    
    return ( new Collection( $data ) )->limit( ...$this->limit );
  }
  
  /**
   * Normalizes query content
   * @return void
   * @throws LogicException
   */
  private function normalize(): void {
    if ( ! $this->table ) {
      throw new LogicException( 'Table must be specified' );
    }
    
    if ( $this->ready ) {
      return;
    }
    
    $this->query[ 'join' ] = $this->query[ 'join' ] ?? [];
    ksort( $this->query[ 'join' ] );
    
    $this->query[ 'map' ] = $this->query[ 'map' ] ?? [];
    sort( $this->query[ 'map' ] );
    
    $this->query[ 'where' ] = $this->query[ 'where' ] ?? [];
    ksort( $this->query[ 'where' ] );
    
    $this->query[ 'select' ] = $this->query[ 'select' ] ?? [];
    ksort( $this->query[ 'select' ] );
    
    $this->query[ 'distinct' ] = $this->query[ 'distinct' ] ?? [];
    sort( $this->query[ 'distinct' ] );
    
    $this->query[ 'sort' ] = $this->query[ 'sort' ] ?? [];
    ksort( $this->query[ 'sort' ] );
    
    $this->query[ 'filter' ] = $this->query[ 'filter' ] ?? [];
    sort( $this->query[ 'filter' ] );
    
    ksort( $this->query );
    $this->ready = true;
  }
  
  /**
   * Returns table and query content
   * @return array
   */
  public function identifier(): array {
    return [ $this->table, $this->query ];
  }
  
  /**
   * Resets query instance
   * @return self
   */
  public function clear(): self {
    $this->query = array();
    $this->cache = true;
    $this->ready = false;
    $this->limit = [ 0, -1 ];
    return $this;
  }
}
