<?php 

namespace App;

use \GuzzleHttp\Client;

class WpPluginEncryption {
    
    public static function alreadyValidated(string $token, string $raw): bool {
        global $wpdb;

        $hash = self::hashForever($raw);

        $gform_unique_id = $_REQUEST['gform_unique_id'];

        $sql = "
            SELECT 
                *
            FROM 
                {$wpdb->prefix}optin_tokens
            WHERE 
                `hash` = '{$hash}'
            AND
                `validated` = 1
            AND
                `used` = 1
            AND
                `token` = '{$token}'
            AND
                `gform_unique_id` = '{$gform_unique_id}'
            ORDER BY id DESC
            ";

        $row = $wpdb->get_row($wpdb->prepare($sql));
        // Will return if validated
        return (bool) $row;
    }

    public static function validate(string $token, string $raw): bool {
        global $wpdb;

        $expires = now()->setTimezone('America/New_York')->toDateTimeString();

        $hash = self::hashForever($raw);

        $sql = "
            SELECT 
                *
            FROM 
                {$wpdb->prefix}optin_tokens
            WHERE 
                `hash` = '{$hash}'
            AND
                `expires_at` >= '{$expires}'
            AND
                `token` = '{$token}'
            ORDER BY id DESC
            ";

        $row = $wpdb->get_row($wpdb->prepare($sql));
        
        if(!$row) return true;

        $updateSql = "
            UPDATE
                {$wpdb->prefix}optin_tokens
            SET
                `used` = 1,
                `validated` = 1
            WHERE 
                `id` = {$row->id}
            ";

        $wpdb->query($wpdb->prepare($updateSql));
        
        return false;
    }

    public static function hashForever(String $raw) {
        return hash('sha384', md5(base64_encode((self::getFirstKey().md5(strtolower($raw))))));
    }

    public static function sendEmailVerification(String $email) {
        return self::sendTokenFor('email', $email);
    }

    public static function sendSmsVerification($phone) {
        return self::sendTokenFor('phone', $phone);
    }

    public static function sendTokenFor(String $service, String $raw): array {
        try {
            $baseUrl = get_option('nexusvc_settings')['api_url'];

            $client = new Client;

            $data = [
                "{$service}" => strtolower($raw),
                'token' => self::generateCode($raw),
                'hash' => self::hashForever($raw),
                'expires_at' => now()->setTimezone('America/New_York')->addMinutes(2)->toDateTimeString(),
                'gform_unique_id' => $_REQUEST['gform_unique_id']
            ];

            $payload = base64_encode(self::encryptPayload(serialize($data)));

            $url     = "{$baseUrl}/{$service}/send?payload={$payload}";
            
            try {
                $request = $client->request('GET', $url);
                $response = $request->getBody(true);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse()->getBody(true);
                error_log("[NXVC][WpPluginEncryption][{$service}]: {$response}");
            }
        
        } catch(\Exception $e) {
            
            error_log("[NXVC][WpPluginEncryption][{$service}]: {$e->getMessage()}");

        }

        unset($data[$service]);

        global $wpdb;
        
        $wpdb->insert( 
            ($wpdb->prefix . 'optin_tokens'),
            $data
        );

        return $data;
    }

    public static function getFirstKey() {
        return 'ifmtOn+6LSHFKdbW5hBiX9Ovn6LNTXLYqqkVMQ8yol0=';
    }

    public static function getSecondKey() {
        return 'W5cOJQ2guDqrkje+W06CFxYu8dHuBXOLX1MAT+VkqvrRw4gg+AXVjzV19Au8aLhQu2j+2mMrpXCCBIXTeKgRpA==';
    }

    public static function generateCode($data, $length = 8) {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes($length / 2);
        } else {
            $bytes = openssl_random_pseudo_bytes($length / 2);
        }
        
        return $randomString = strtoupper(bin2hex($bytes));
    }
    
    public static function decryptPayload($input) {
        $firstKey = self::getFirstKey();
        $secondKey = self::getSecondKey();

        $first_key = base64_decode($firstKey);
        $second_key = base64_decode($secondKey);

        $mix = base64_decode($input);
                
        $method = "aes-256-cbc";    
        $iv_length = openssl_cipher_iv_length($method);
                    
        $iv = substr($mix,0,$iv_length);
        $second_encrypted = substr($mix,$iv_length,64);
        $first_encrypted = substr($mix,$iv_length+64);
                    
        $data = openssl_decrypt($first_encrypted,$method,$first_key,OPENSSL_RAW_DATA,$iv);

        $second_encrypted_new = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
            
        if(hash_equals($second_encrypted,$second_encrypted_new)) return $data;
            
        return false;
    }

    public static function encryptPayload($data) {

        $firstKey = self::getFirstKey();
        $secondKey = self::getSecondKey();

        $first_key = base64_decode($firstKey);
        $second_key = base64_decode($secondKey);    
            
        $method = "aes-256-cbc";    
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
                
        $first_encrypted = openssl_encrypt($data,$method,$first_key, OPENSSL_RAW_DATA ,$iv);    
        $second_encrypted = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
                    
        $output = base64_encode($iv.$second_encrypted.$first_encrypted);    
        return $output;        
    }
}
