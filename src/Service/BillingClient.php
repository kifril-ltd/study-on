<?php

namespace App\Service;

use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Dto\Response\UserAuthResponseDto;
use App\Exception\BillingException;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class BillingClient
{
    protected $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function auth($credentials)
    {
        $api = new ApiService(
            '/api/v1/auth',
            'POST',
            json_decode($credentials, true),
            null,
            null,
            'Сервис авторизации недоступен. Попробуйте авторизоваться позже.'
        );
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            if ($result['code'] === 401) {
                throw new UserNotFoundException('Проверьте правильность введённого логина и пароля');
            }
        }

        $userDto = $this->serializer->deserialize($response, UserAuthResponseDto::class, 'json');

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
            ],
            'Сервис биллинга недоступен.'
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
        $api = new ApiService(
            '/api/v1/register',
            'POST',
            $registerRequest,
            null,
            null,
            'Сервис регистрации недоступен. Попробуйте зарегистрироваться позже.');
        $response = $api->exec();
        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        $userDto = $this->serializer->deserialize($response, UserAuthResponseDto::class, 'json');

        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

    public function refreshToken($refreshToken)
    {
        $api = new ApiService(
            '/api/v1/token/refresh',
            'POST',
            ['refresh_token' => $refreshToken],
            null,
            null,
            'Сервис биллинга недоступен.');
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, 'array', 'json');
    }

    public function getAllCourses()
    {
        $api = new ApiService(
            '/api/v1/courses/',
            'GET',
            null,
            null,
            null,
            'Сервис биллинга недоступен.');
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, 'array', 'json');
    }

    public function getCourseByCode($courseCode)
    {
        $api = new ApiService(
            '/api/v1/courses/' . $courseCode,
            'GET',
            null,
            null,
            null,
            'Сервис биллинга недоступен.');
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, 'array', 'json');
    }

    public function getTransactions($filters, $token)
    {
        $api = new ApiService(
            '/api/v1/transactions/',
            'GET',
            null,
            $filters,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
            'Сервис биллинга недоступен.');
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, 'array', 'json');
    }

    public function pay($courseCode, $token)
    {
        $api = new ApiService(
            '/api/v1/courses/' . $courseCode . '/pay',
            'POST',
            null,
            null,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
            'Сервис биллинга недоступен.');
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['status_code']) && $result['status_code'] !== Response::HTTP_OK) {
            throw new BillingException($result['message']);
        }

        return $this->serializer->deserialize($response, 'array', 'json');
    }
}