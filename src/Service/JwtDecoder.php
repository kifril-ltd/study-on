<?php

namespace App\Service;

class JwtDecoder
{
    public static function decode($token)
    {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        return [
            'exp' => $payload['exp'],
            'username' => $payload['email'],
            'roles' => $payload['roles']
        ];
    }

}