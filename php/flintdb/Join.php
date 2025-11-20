<?php

namespace FlintDB;

class Join {
  /**
   * The right-table to join
   *
   * @var Table
   */
  private Table $table;
  
  /**
   * The join clause
   *
   * @var array
   */
  private array $options;
  
  /**
   * Instance of Where class
   *
   * @var Where
   */
  private Where $where;
  
  /**
   * Constructor
   *
   * @param Row $row
   */
  public function __construct( Row $row ) {
    $this->where = new Where( $row );
  }
  
  /**
   * Sets the right-table to join
   *
   * @param Table $table
   * @return void
   */
  public function table( Table $table ): void {
    $this->table = $table;
  }
  
  /**
   * Sets the join clause
   *
   * @param array $options
   * @return void
   */
  public function options( array $options ): void {
    $this->options = $options;
  }
  
  /**
   * Filter for Where instance
   *
   * @param Row $row
   * @return bool
   */
  public function filter( Row $row ): bool {
    $column = $this->options[0];
    $operator = $this->options[1];
    $value = $row[ $this->options[2] ];
    $this->where->criteria( $column, $operator, $value );
    return $this->where->match();
  }
  
  /**
   * Returns matching row
   *
   * @return ?Row
   */
  public function row(): ?Row {
    $query = $this->table->query();
    $query->filter( [ $this, 'filter' ] );
    $query->no_cache();
    
    $data = $query->fetch();
    return $data->item(0);
  }
}
