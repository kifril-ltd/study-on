<?php

namespace App\Tests\Mock;

use App\Dto\Request\UserAuthRequestDto;
use App\Dto\Request\UserRegisterDto;
use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\UserAuthResponseDto;
use App\Dto\UserDto;
use App\Exception\BillingException;
use App\Service\BillingClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class BillingClientMock extends BillingClient
{
    private $user;
    private $userAdmin;
    private $courses;
    private $transactions;

    public function __construct(SerializerInterface $serializer)
    {
        parent::__construct($serializer);

        $this->user = new UserDto();
        $this->user->username = 'user@study-on.local';
        $this->user->password = 'Qwerty123';
        $this->user->roles = ['ROLE_USER'];
        $this->user->balance = 1000;

        $this->userAdmin = new UserDto();
        $this->userAdmin->username = 'admin@study-on.local';
        $this->userAdmin->password = 'Qwerty123';
        $this->userAdmin->roles = ['ROLE_USER', 'ROLE_SUPER_ADMIN'];
        $this->userAdmin->balance = 2000;

        $courseTypes = [
            1 => 'rent',
            2 => 'free',
            3 => 'buy'
        ];

        $this->courses = [
            [
                'code' => 'PPBIB',
                'type' => $courseTypes[2],
                'price' => 2000,
            ],
            [
                'code' => 'PPBI',
                'type' => $courseTypes[1],
                'price' => 2000,
            ],
            [
                'code' => 'PPBI2',
                'type' => $courseTypes[3],
                'price' => 2000,
            ],
            [
                'code' => 'MSCB',
                'type' => $courseTypes[2],
                'price' => 1000,
            ],
            [
                'code' => 'MSC',
                'type' => $courseTypes[3],
                'price' => 1000,
            ],
            [
                'code' => 'CAMPB',
                'type' => $courseTypes[2],
                'price' => 3000,
            ],
            [
                'code' => 'CAMP',
                'type' => $courseTypes[1],
                'price' => 3000,
            ],
        ];

        $transactionTypes = [
            1 => 'payment',
            2 => 'deposit'
        ];

        $this->transactions = [
            [
                'type' => $transactionTypes[2],
                'amount' => 10000,
                'created_at' => new \DateTimeImmutable('2022-06-01 00:00:00'),
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'created_at' => new \DateTimeImmutable('2022-06-05 00:00:00'),
                'course_code' => 'MSC'
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'created_at' => new \DateTimeImmutable('2022-06-08 00:00:00'),
                'course_code' => 'PPBI',
                'expires_at' => (new \DateTimeImmutable('2022-06-08 00:00:00'))->add(new \DateInterval('P1W'))
            ],
            [
                'type' => $transactionTypes[1],
                'amount' => 1000,
                'created_at' => new \DateTimeImmutable(),
                'course_code' => 'PPBI',
                'expires_at' => (new \DateTimeImmutable())->add(new \DateInterval('P1W'))
            ],
        ];

        $this->user->balance = $this->transactions[0]['amount'] -
            ($this->transactions[1]['amount'] + $this->transactions[2]['amount'] + $this->transactions[3]['amount']);
    }

    public function auth($request)
    {
        /** @var UserAuthRequestDto $userAuthRequest */
        $userAuthRequest = $this->serializer->deserialize($request, UserAuthRequestDto::class, 'json');
        if (
            $userAuthRequest->username === $this->user->username &&
            $userAuthRequest->password === $this->user->password
        ) {
            $userAuthResponse = new UserAuthResponseDto();
            $userAuthResponse->token = $this->generateToken('ROLE_USER', $this->user->username);
            return $userAuthResponse;
        }

        if (
            $userAuthRequest->username === $this->userAdmin->username &&
            $userAuthRequest->password === $this->userAdmin->password
        ) {
            $userAuthResponse = new UserAuthResponseDto();
            $userAuthResponse->token = $this->generateToken('ROLE_SUPER_ADMIN', $this->user->username);
            return $userAuthResponse;
        }

        throw new UserNotFoundException('Проверьте правильность введённого логина и пароля');
    }

    /** @var UserRegisterDto $registerRequest */
    public function register($registerRequest)
    {
        if (
            $registerRequest->username === $this->user->username ||
            $registerRequest->username === $this->userAdmin->username
        ) {
            throw new BillingException('Пользователь с таким именем уже существует.');
        }

        $registerResponse = new UserAuthResponseDto();
        $registerResponse->token = $this->generateToken('ROLE_USER', $registerRequest->username);
        return $registerResponse;
    }

    public function getUser($token)
    {
        $userData = $this->jwtDecode($token);
        $currentUser = new CurrentUserDto();
        $currentUser->username = $userData['username'];
        $currentUser->roles = $userData['roles'];
        $currentUser->balance = 0;
        if ($currentUser->username === $this->user->username) {
            $currentUser->balance = $this->user->balance;
        }
        if ($currentUser->username === $this->userAdmin->username) {
            $currentUser->balance = $this->userAdmin->balance;
        }
        return $currentUser;
    }

    private function jwtDecode($token)
    {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        return [
            'username' => $payload['email'],
            'roles' => $payload['roles']
        ];
    }

    private function generateToken(string $role, string $username): string
    {
        $roles = null;
        if ($role === 'ROLE_USER') {
            $roles = ["ROLE_USER"];
        } elseif ($role === 'ROLE_SUPER_ADMIN') {
            $roles = ["ROLE_SUPER_ADMIN", "ROLE_USER"];
        }
        $data = [
            'username' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $query = base64_encode(json_encode($data));
        return 'header.' . $query . '.signature';
    }

    public function getAllCourses()
    {
        return $this->courses;
    }

    public function getCourseByCode($courseCode)
    {
        foreach ($this->courses as $course) {
            if ($course['code'] === $courseCode) {
                return $course;
            }
        }
    }

    public function getTransactions($filters, $token)
    {
        if ($token === '') {
            throw new AccessDeniedException();
        }

        $user = $this->jwtDecode($token);

        if ($user['username'] === $this->userAdmin->username) {
            return [];
        }

        $filteredTransactions = $this->transactions;

        if (isset($filters['type'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return $transaction['type'] === $filters['type'];
            });
        }

        if (isset($filters['course_code'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return $transaction['course_code'] === $filters['course_code'];
            });
        }

        if (isset($filters['skip_expired'])) {
            $filteredTransactions = array_filter($filteredTransactions, function ($transaction) use ($filters) {
                return $transaction['expires_at'] > new \DateTimeImmutable();
            });
        }

        return $filteredTransactions;
    }
}
