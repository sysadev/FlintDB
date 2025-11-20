<?php

namespace FlintDB;

class Crypto {
  /**
   * Cipher method
   * @var string
   */
  const METHOD = 'AES-256-CBC';
  
  /**
   * Returns encrypted data
   * @param mixed $data
   * @param string $phrase
   * @return string
   */
  public static function encrypt( mixed $data, #[\SensitiveParameter] string $phrase ): string {
    $data = json_encode( $data );
    $iv_len = openssl_cipher_iv_length( self::METHOD );
    $iv = openssl_random_pseudo_bytes( $iv_len );
    $cipher = openssl_encrypt( $data, self::METHOD, $phrase, OPENSSL_RAW_DATA, $iv );
    $hmac = hash_hmac( 'sha256', $cipher, $phrase, true );
    return base64_encode( $iv . $hmac . $cipher );
  }
  
  /**
   * Returns decrypted data
   * @param string $hash
   * @param string $phrase
   * @return mixed
   */
  public static function decrypt( string $hash, #[\SensitiveParameter] string $phrase ): mixed {
    $hash = base64_decode( $hash );
    $iv_len = openssl_cipher_iv_length( self::METHOD );
    $iv = substr( $hash, 0, $iv_len );
    $hmac = substr( $hash, $iv_len, 32 );
    $ciphertext = substr( $hash, $iv_len + 32 );
    $original = openssl_decrypt( $ciphertext, self::METHOD, $phrase, OPENSSL_RAW_DATA, $iv );
    $calcmac = hash_hmac( 'sha256', $ciphertext, $phrase, true );
    if ( hash_equals( $hmac, $calcmac ) ) {
      return json_decode( $original, true );
    }
    
    return null;
  }
  
  /**
   * Returns random hex
   * @param int $nbytes
   * @return string
   */
  public static function random_id( int $nbytes = 32 ): string {
    return bin2hex( random_bytes( $nbytes ) );
  }
}
