<?php

namespace FlintDB;

use ArrayIterator,
    Countable,
    Generator,
    IteratorAggregate,
    LimitIterator;

class Collection implements Countable, IteratorAggregate {
  /**
   * Position of the first item to return
   *
   * @var int
   */
  private int $offset = 0;
  
  /**
   * Position of the last item to return
   *
   * @var int
   */
  private int $limit = -1;
  
  /**
   * The items iterator instance
   *
   * @var iterable
   */
  private iterable $iter;
  
  /**
   * Constructor
   *
   * @param iterable $iter
   */
  public function __construct( iterable $iter ) {
    if ( is_array( $iter ) ) {
      $iter = new ArrayIterator( $iter );
    }
    
    $this->iter = $iter;
  }
  
  /**
   * Returns item at a position
   *
   * @param int $position
   * @return mixed
   */
  public function item( int $position ): mixed {
    return $this->iter->offsetExists( $position ) ? $this->iter->offsetGet( $position ) : null;
  }
  
  /**
   * Sets offset and limit values
   *
   * @param int $offset
   * @param int $limit
   * @return self
   */
  public function limit( int $offset, int $limit ): self {
    $this->offset = $offset;
    $this->limit  = $limit;
    return $this;
  }
  
  /**
   * Returns number of items in external iterator
   *
   * @return int
   */
  public function count(): int {
    return iterator_count( $this );
  }
  
  /**
   * Returns total number of items
   *
   * @return int
   */
  public function total_count(): int {
    return iterator_count( $this->iter );
  }
  
  /**
   * Returns external iterator
   *
   * @return Generator
   */
  public function getIterator(): Generator {
    yield from new LimitIterator(
      $this->iter,
      $this->offset,
      $this->limit
    );
  }
}
