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

        $submitButton = $crawler->selectButton('Sign in');
        $form = $submitButton->form([
            'username' => $userDto->username,
            'password' => $userDto->password
        ]);
        $crawler = $client->submit($form);

        $error = $crawler->filter('#errors');
        self::assertCount(0, $error);

        $crawler = $client->followRedirect();
        file_put_contents('log1.html', $crawler->html());
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
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