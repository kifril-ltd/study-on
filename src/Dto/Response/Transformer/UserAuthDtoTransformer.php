<?php

namespace App\Dto\Response\Transformer;

use App\Dto\Response\UserAuthResponseDto;
use App\Security\User;

class UserAuthDtoTransformer
{
    public function transformToObject(UserAuthResponseDto $userAuthDto)
    {
        $user = new User();
        $user->setApiToken($userAuthDto->token);
        $user->setRefreshToken($userAuthDto->refreshToken);

        $decodedJwt = $this->jwtDecode($userAuthDto->token);
        $user->setRoles($decodedJwt['roles']);
        $user->setEmail($decodedJwt['email']);

        return $user;
    }

    private function jwtDecode($token)
    {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        return [
            'email' => $payload['email'],
            'roles' => $payload['roles']
        ];
    }
}