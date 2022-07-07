<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;

class UserDto
{
    #[Serializer\Type("string")]
    public string $username;

    #[Serializer\Type("string")]
    public string $password;

    #[Serializer\Type("array")]
    public array $roles;

    #[Serializer\Type("float")]
    public float $balance;
}