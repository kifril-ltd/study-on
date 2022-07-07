<?php

namespace App\Tests\Authentication;

use App\Dto\Request\UserAuthRequestDto;
use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;

class AuthTest extends AbstractTest
{
    private $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function auth(string $data)
    {
        /** @var UserAuthRequestDto $userDto */
        $userDto = $this->serializer->deserialize($data, UserAuthRequestDto::class, 'json');
        $this->getBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/login');
        $this->assertResponseOk();

        $form = $crawler->selectButton('Sign In')->form();
        $form['email'] = $userDto->username;
        $form['password'] = $userDto->password;
        $client->submit($form);

        $error = $crawler->filter('#errors');
        self::assertCount(0, $error);

        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        return $crawler;
    }


    public function getBillingClient(): void
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock($this->serializer)
        );
    }
}