<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Authentication\AuthTest;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;

class SecurityControllerTest extends AbstractTest
{
    private $userAuthData = [
        'username' => 'user@study-on.local',
        'password' => 'Qwerty123'
    ];

    private $adminAuthData = [
        'username' => 'admin@study-on.local',
        'password' => 'Qwerty123'
    ];

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    private function mockBillingClient()
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock($this->serializer)
        );
    }

    public function testAuthWithValidCreditals()
    {
        $this->mockBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/');
        $linkLogin = $crawler->selectLink('Войти')->link();
        $crawler = $client->click($linkLogin);
        $this->assertResponseOk();

        $loginForm = $crawler->selectButton('Sign in')->form();
        $loginForm['username'] = $this->userAuthData['username'];
        $loginForm['password'] = $this->userAuthData['password'];
        $client->submit($loginForm);

        $this->assertResponseRedirect();
        $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
    }

    public function testAuthWithInvalidCreditals()
    {
        $this->mockBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/');
        $linkLogin = $crawler->selectLink('Войти')->link();
        $crawler = $client->click($linkLogin);
        $this->assertResponseOk();

        $loginForm = $crawler->selectButton('Sign in')->form();
        $loginForm['username'] = $this->userAuthData['username'];
        $loginForm['password'] = $this->userAuthData['password'] . '123';
        $client->submit($loginForm);

        self::assertFalse($client->getResponse()->isRedirect('/courses/'));

        $crawler = $client->followRedirect();
        $error = $crawler->filter('.alert');
        self::assertEquals('Недействительные аутентификационные данные.', $error->text());
    }

    public function testRegisterWithValidFields()
    {
        $this->mockBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/');
        $this->assertResponseOk();

        $registerLink = $crawler->selectLink('Регистрация')->link();
        $crawler = $client->click($registerLink);
        $this->assertResponseOk();

        $registerForm = $crawler->selectButton('Зарегистрироваться')->form();
        $registerForm['register[username]'] = 'newuser@test.com';
        $registerForm['register[password][password]'] = 'testPassword';
        $registerForm['register[password][password_repeat]'] = 'testPassword';
        $client->submit($registerForm);

        $this->assertResponseRedirect();
        $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
    }

    public function testRegisterWithInvalidFields()
    {
        $this->mockBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/');
        $this->assertResponseOk();

        $registerLink = $crawler->selectLink('Регистрация')->link();
        $crawler = $client->click($registerLink);
        $this->assertResponseOk();

        $registerForm = $crawler->selectButton('Зарегистрироваться')->form();
        $registerForm['register[username]'] = 'newuser@testcom';
        $registerForm['register[password][password]'] = 'tes';
        $registerForm['register[password][password_repeat]'] = 'tes';
        $crawler = $client->submit($registerForm);

        self::assertFalse($client->getResponse()->isRedirect('/courses/'));

        $errors = $crawler->filter('.invalid-feedback')->each(function (Crawler $crawler) {
            return $crawler->text();
        });
        self::assertContains('Значение адреса электронной почты недопустимо.', $errors);
        self::assertContains('Значение слишком короткое. Должно быть равно 6 символам или больше.', $errors);

        $registerForm = $crawler->selectButton('Зарегистрироваться')->form();
        $registerForm['register[username]'] = 'newuser@testcom';
        $registerForm['register[password][password]'] = 'tesdt';
        $registerForm['register[password][password_repeat]'] = 'tes';
        $crawler = $client->submit($registerForm);

        self::assertFalse($client->getResponse()->isRedirect('/courses/'));

        $errors = $crawler->filter('.invalid-feedback')->each(function (Crawler $crawler) {
            return $crawler->text();
        });
        self::assertContains('Значение адреса электронной почты недопустимо.', $errors);
        self::assertContains('Значения не совпадают.', $errors);
    }
}
