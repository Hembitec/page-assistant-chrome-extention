<?php

class Hmb_Pa_Jwt_Handler {

    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generate($user_id) {
        $secret_key = get_option('hmb_pa_jwt_secret_key');
        if (empty($secret_key)) {
            $secret_key = 'default-secret-key-please-change-in-settings';
        }
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(['user_id' => $user_id, 'iat' => time(), 'exp' => time() + (7 * 24 * 60 * 60)]); // 7-day expiration

        $base64UrlHeader = self::base64url_encode($header);
        $base64UrlPayload = self::base64url_encode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret_key, true);
        $base64UrlSignature = self::base64url_encode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validate($jwt) {
        $secret_key = get_option('hmb_pa_jwt_secret_key');
        if (empty($secret_key)) {
            return null;
        }
        
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        list($header, $payload, $signature) = $parts;

        $decoded_signature = self::base64url_decode($signature);
        $expected_signature = hash_hmac('sha256', $header . "." . $payload, $secret_key, true);

        if (!hash_equals($decoded_signature, $expected_signature)) {
            return null;
        }

        $decoded_payload = json_decode(self::base64url_decode($payload));

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded_payload->exp) || $decoded_payload->exp < time()) {
            return null;
        }

        return $decoded_payload;
    }
}
