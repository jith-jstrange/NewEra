<?php
/**
 * Crypto class for secure data encryption/decryption
 *
 * Implements AES-256-CBC encryption using OpenSSL with WordPress salts/keys,
 * IV management, and input validation.
 */

namespace Newera\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crypto class for secure encryption and decryption
 */
class Crypto {
    /**
     * Cipher method for encryption
     */
    const CIPHER_METHOD = 'aes-256-cbc';
    
    /**
     * Minimum length for encryption keys
     */
    const MIN_KEY_LENGTH = 32;
    
    /**
     * Expected IV length for AES-256-CBC
     */
    const IV_LENGTH = 16;
    
    /**
     * Get encryption key from WordPress salts
     *
     * @return string
     * @throws \Exception If unable to generate secure key
     */
    private function get_encryption_key() {
        // Get WordPress salts
        $key = defined('AUTH_KEY') ? AUTH_KEY : '';
        $key .= defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';
        $key .= defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '';
        $key .= defined('NONCE_KEY') ? NONCE_KEY : '';
        $key .= defined('AUTH_SALT') ? AUTH_SALT : '';
        $key .= defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '';
        $key .= defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : '';
        $key .= defined('NONCE_SALT') ? NONCE_SALT : '';
        
        // Ensure we have a minimum key length
        if (empty($key) || strlen($key) < self::MIN_KEY_LENGTH) {
            // Fallback to site-specific key generation
            $site_key = get_site_option('newera_crypto_key');
            if (empty($site_key)) {
                $site_key = $this->generate_secure_key();
                add_site_option('newera_crypto_key', $site_key, '', 'no');
            }
            $key = $site_key;
        }
        
        // Derive a 256-bit key using PBKDF2
        return hash_pbkdf2('sha256', $key, 'newera_salt', 10000, 32, true);
    }
    
    /**
     * Generate a cryptographically secure key
     *
     * @return string
     * @throws \Exception If secure random generation fails
     */
    private function generate_secure_key() {
        $key = '';
        if (function_exists('random_bytes')) {
            $key = random_bytes(32);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $key = openssl_random_pseudo_bytes(32, $crypto_strong);
            if (!$crypto_strong) {
                throw new \Exception('OpenSSL could not generate a cryptographically secure key');
            }
        } else {
            throw new \Exception('No secure random generation method available');
        }
        
        return bin2hex($key);
    }
    
    /**
     * Generate a random initialization vector
     *
     * @return string
     * @throws \Exception If IV generation fails
     */
    private function generate_iv() {
        if (function_exists('random_bytes')) {
            return random_bytes(self::IV_LENGTH);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $iv = openssl_random_pseudo_bytes(self::IV_LENGTH, $crypto_strong);
            if (!$crypto_strong) {
                throw new \Exception('OpenSSL could not generate a cryptographically secure IV');
            }
            return $iv;
        } else {
            throw new \Exception('No secure IV generation method available');
        }
    }
    
    /**
     * Validate input data for encryption
     *
     * @param mixed $data
     * @return bool
     */
    private function validate_input($data) {
        if ($data === null || $data === false) {
            return false;
        }
        
        if (is_string($data) && trim($data) === '') {
            return false;
        }
        
        if (is_array($data) && empty($data)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Encrypt data using AES-256-CBC
     *
     * @param mixed $data The data to encrypt
     * @return array|false Encrypted data array with iv, tag, and encrypted data, or false on failure
     */
    public function encrypt($data) {
        // Validate input
        if (!$this->validate_input($data)) {
            return false;
        }
        
        // Convert data to JSON string for consistent handling
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            return false;
        }
        
        try {
            $key = $this->get_encryption_key();
            $iv = $this->generate_iv();
            
            // Encrypt the data
            $encrypted = openssl_encrypt(
                $json_data,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                return false;
            }
            
            // Combine IV and encrypted data
            $result = [
                'iv' => base64_encode($iv),
                'data' => base64_encode($encrypted),
                'version' => '1.0',
                'timestamp' => time()
            ];
            
            return $result;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Decrypt data encrypted with encrypt()
     *
     * @param array $encrypted_data Encrypted data array
     * @return mixed|false The original data, or false on failure
     */
    public function decrypt($encrypted_data) {
        // Validate input structure
        if (!is_array($encrypted_data) || !isset($encrypted_data['iv']) || !isset($encrypted_data['data'])) {
            return false;
        }
        
        try {
            $key = $this->get_encryption_key();
            
            // Decode the base64 encoded data
            $iv = base64_decode($encrypted_data['iv']);
            $encrypted = base64_decode($encrypted_data['data']);
            
            // Validate IV length
            if (strlen($iv) !== self::IV_LENGTH) {
                return false;
            }
            
            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                return false;
            }
            
            // Parse JSON data back to original format
            $data = json_decode($decrypted, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                // Fallback to return as string if JSON parsing fails
                return $decrypted;
            }
            
            return $data;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if encryption is available and supported
     *
     * @return bool
     */
    public function is_available() {
        // Check if OpenSSL extension is loaded
        if (!extension_loaded('openssl')) {
            return false;
        }
        
        // Check if the cipher method is supported
        if (!in_array(self::CIPHER_METHOD, openssl_get_cipher_methods())) {
            return false;
        }
        
        // Check if secure random generation is available
        if (!function_exists('random_bytes') && !function_exists('openssl_random_pseudo_bytes')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get encryption metadata
     *
     * @param array $encrypted_data
     * @return array
     */
    public function get_metadata($encrypted_data) {
        if (!is_array($encrypted_data)) {
            return [];
        }
        
        return [
            'version' => isset($encrypted_data['version']) ? $encrypted_data['version'] : 'unknown',
            'timestamp' => isset($encrypted_data['timestamp']) ? $encrypted_data['timestamp'] : null,
            'iv_length' => isset($encrypted_data['iv']) ? strlen(base64_decode($encrypted_data['iv'])) : 0,
            'data_size' => isset($encrypted_data['data']) ? strlen(base64_decode($encrypted_data['data'])) : 0
        ];
    }
}