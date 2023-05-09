<?php 

namespace App\Traits;

use \GuzzleHttp\Client;

class WpPluginEncryption {

    public static function sendEmailVerification($email) {
        $client = new Client;

        $data = [
            'email' => $email,
            'code' => self::generateCode($email)
        ];

        $payload = base64_encode(self::encryptPayload(serialize($data)));
        $url     = "https://api.livingdonorproject.org/test/email/send?payload={$payload}";
        try {
            $request = $client->request('GET', $url);
            $response = $request->getBody(true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse()->getBody(true);
        }
    }

    public static function sendSmsVerification($phone) {
        $client = new Client;

        $data = [
            'phone' => $phone,
            'code' => self::generateCode($phone)
        ];

        $payload = base64_encode(self::encryptPayload(serialize($data)));
        $url     = "https://api.livingdonorproject.org/test/phone/send?payload={$payload}";
        try {
            $request = $client->request('GET', $url);
            $response = $request->getBody(true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse()->getBody(true);
        }
    }

    public static function getFirstKey() {
        return 'ifmtOn+6LSHFKdbW5hBiX9Ovn6LNTXLYqqkVMQ8yol0=';
    }

    public static function getSecondKey() {
        return 'W5cOJQ2guDqrkje+W06CFxYu8dHuBXOLX1MAT+VkqvrRw4gg+AXVjzV19Au8aLhQu2j+2mMrpXCCBIXTeKgRpA==';
    }

    public static function build($data, $type) {
        return serialize([
            'data' => strtolower($data),
            'type'    => strtolower($type),
        ]);
    }

    public static function unbuild($data) {
        return unserialize($data);
    }

    public static function generateCode($data) {
        $data = strtolower(serialize($data));
        $date  = date('Y-m-d');
        return $code  = substr(strtoupper(md5("LDP::{$data}{$date}")), -6);
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
            
        if (hash_equals($second_encrypted,$second_encrypted_new))
        return $data;
            
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
