<?php

namespace App\Service;

use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Dto\Response\UserAuthDto;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\SerializerInterface;

class BillingClient
{
    private string $apiUrl;
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->apiUrl = $_ENV['BILLING_URL'];
        $this->serializer = $serializer;
    }

    public function auth($credentials)
    {
        $query = curl_init($this->apiUrl . '/api/v1/auth');
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $credentials,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($credentials),
            ]
        ];
        curl_setopt_array($query, $options);
        $response = curl_exec($query);

        if ($response === false) {
            throw new BillingUnavailableException('Ошибка на стороне сервиса авторизации');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            if ($result['code'] === 401) {
                throw new BillingUnavailableException('Проверьте правильность введённого логина и пароля');
            }
        }

        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');
        $user = (new UserAuthDtoTransformer())->transformToObject($userDto);

        return $user;
    }

    public function getUser($token) {
        $query = curl_init($this->apiUrl . '/api/v1/users/current');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]
        ];
        curl_setopt_array($query, $options);
        $response = curl_exec($query);
        if ($response === false) {
            throw new BillingUnavailableException('Ошибка на стороне сервиса авторизации');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingUnavailableException('Ошибка на стороне сервера.');
        }

        return $this->serializer->deserialize($response, CurrentUserDto::class, 'json');
    }
}