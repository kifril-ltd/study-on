<?php

namespace App\Dto\Response;

use JMS\Serializer\Annotation as Serializer;

class UserAuthResponseDto
{

    #[Serializer\Type("string")]
    public string $token;
}