<?php

namespace App\Dto\Response\Transformer;

use App\Dto\Response\UserAuthDto;
use App\Security\User;

class UserAuthDtoTransformer
{
    public function transformToObject(UserAuthDto $userAuthDto)
    {
        $user = new User();
        $user->setApiToken($userAuthDto->token);

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