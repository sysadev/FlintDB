<?php

namespace FlintDB;

class Where {
  /**
   * The filtering criteria
   *
   * @var array
   */
  private array $criteria;
  
  /**
   * The row to filter
   *
   * @var Row
   */
  private Row $row;
  
  /**
   * Constructor
   *
   * @param Row $row
   */
  public function __construct( Row $row ) {
    $this->row = $row;
  }
  
  /**
   * Sets filtering criteria
   *
   * @param string $column
   * @param string $operator
   * @param mixed $value
   * @return void
   */
  public function criteria( string $column, string $operator, mixed $value ): void {
    $this->criteria = [ $column, $operator, $value ];
  }
  
  /**
   * Returns whether conditions are met
   *
   * @return bool
   */
  public function match(): bool {
    try {
      $value = $this->criteria[2];
      $row_value = $this->row[ $this->criteria[0] ];
      switch ( $this->criteria[1] ) {
        case '=':
        case 'eq':
        case 'is':
          return $row_value === $value;
        case '!=':
        case 'neq':
        case 'is not':
          return $row_value !== $value;
        case '>':
        case 'gt':
          return $row_value > $value;
        case '>=':
        case 'gte':
          return $row_value >= $value;
        case '<':
        case 'lt':
          return $row_value < $value;
        case '<=':
        case 'lte':
          return $row_value <= $value;
        case 'in':
        case 'is in':
          return match ( gettype( $value ) ) {
            'array' => in_array( $row_value, $value ),
            'string' => str_contains( $value, $row_value )
          };
        case 'not in':
          return match ( gettype( $value ) ) {
            'array' => ! in_array( $row_value, $value ),
            'string' => ! str_contains( $value, $row_value )
          };
        case 'between':
          return $row_value >= ( $value[0] ?? null ) && $row_value <= ( $value[1] ?? null );
        case 'not between':
          return $row_value <= ( $value[0] ?? null ) && $row_value >= ( $value[1] ?? null );
        case 'like':
          $pattern = preg_quote( $value, '/' );
          if ( ! str_contains( $pattern, '%' ) && ! str_contains( $pattern, '_' ) ) {
            return $row_value === $value;
          }
          
          $pattern = str_replace( '%', '.*', $pattern );
          $pattern = str_replace( '_', '.', $pattern );
          return (bool) preg_match( '/^' . $pattern . '$/', $row_value );
        case 'not like':
          $pattern = preg_quote( $value, '/' );
          if ( ! str_contains( $pattern, '%' ) && ! str_contains( $pattern, '_' ) ) {
            return $row_value !== $value;
          }
          
          $pattern = str_replace( '%', '.*', $pattern );
          $pattern = str_replace( '_', '.', $pattern );
          return ! preg_match( '/^' . $pattern . '$/', $row_value );
        default:
          return false;
      }
    } catch ( Throwable $err ) {
      return false;
    }
  }
}
