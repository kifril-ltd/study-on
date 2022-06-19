<?php

namespace App\Service;

use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Dto\Response\UserAuthDto;
use App\Exception\BillingException;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

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

        $api = new ApiService('/api/v1/auth', 'POST', json_decode($credentials, true));
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            if ($result['code'] === 401) {
                throw new UserNotFoundException('Проверьте правильность введённого логина и пароля');
            }
        }

        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');

        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

    public function getUser($token)
    {
        $api = new ApiService(
            '/api/v1/users/current',
            'GET',
            null,
            null,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]
        );
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, CurrentUserDto::class, 'json');
    }

    public function register($registerRequest)
    {
        $api = new ApiService('/api/v1/register', 'POST', $registerRequest);
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');

        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }
}