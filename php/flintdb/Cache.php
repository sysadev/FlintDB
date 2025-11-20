<?php

namespace FlintDB;

class Cache {
  /**
   * The instance of table for the data to cache
   *
   * @var Table
   */
  private Table $table;
  
  /**
   * The query content
   *
   * @var array
   */
  private array $query;
  
  /**
   * The date timestamp for when cache will expire
   *
   * @var int|null
   */
  private ?int $expiration;
  
  /**
   * The cache directory
   *
   * @var string
   */
  private string $folder;
  
  /**
   * Constructor
   *
   * @param Table $table
   * @param array $query
   * @param int|null $expiration
   */
  public function __construct( Table $table, array $query = array(), ?int $expiration = null ) {
    $this->table = $table;
    $this->query = $query;
    $this->expiration = $expiration;
    
    $folder = str_replace( $this->table->name(), '', $this->table->folder() );
    $this->folder = Filesystem::join( $folder, '.cache', $this->table->name() );
  }
  
  /**
   * Returns cache file path
   *
   * @return string
   */
  public function file(): string {
    $hash = hash( 'xxh3', serialize( $this->query ) );
    return Filesystem::join( $this->folder, $hash );
  }
  
  /**
   * Returns whether cache is valid
   *
   * @return bool
   */
  public function valid(): bool {
    $file = $this->file();
    if ( ! is_file( $file ) ) {
      return false;
    }
    
    elseif ( is_null( $this->expiration ) ) {
      return true;
    }
    
    elseif ( filectime( $file ) > $this->expiration ) {
      unlink( $file );
      return false;
    }
    
    return true;
  }
  
  /**
   * Writes data to cache file
   *
   * @param array $data
   * @return bool
   * @throws RuntimeException
   */
  public function put( array $data ): bool {
    if ( ! is_dir( $this->folder ) && ! mkdir( $this->folder, recursive: true ) ) {
      throw new RuntimeException( 'Failed to create cache directory' );
    }
    
    $data = gzencode( serialize( $data ), 6 );
    return Filesystem::write( $this->file(), $data );
  }
  
  /**
   * Returns cached data
   *
   * @return array
   */
  public function get(): array {
    $data = Filesystem::read( $this->file() );
    return unserialize( gzdecode( $data ) );
  }
  
  /**
   * Deletes cache file
   *
   * @return bool
   */
  public function delete(): bool {
    return unlink( $this->file() );
  }
  
  /**
   * Deletes all cache files related to the table
   *
   * @return void
   */
  public function flush(): void {
    Filesystem::rmtree( $this->folder );
  }
}
