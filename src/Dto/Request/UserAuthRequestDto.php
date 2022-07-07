<?php

namespace App\Dto\Request;

use JMS\Serializer\Annotation as Serializer;

class UserAuthRequestDto
{
    #[Serializer\Type("string")]
    public string $username;

    #[Serializer\Type("string")]
    public string $password;
}