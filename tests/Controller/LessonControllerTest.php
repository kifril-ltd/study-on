<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Tests\AbstractTest;
use App\Tests\Authentication\AuthTest;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;

class LessonControllerTest extends AbstractTest
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

    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    public function testLessonPagesResponseIsSuccessful(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            foreach ($course->getLessons() as $lesson) {
                $client->request('GET', '/lessons/' . $lesson->getId());
                $this->assertResponseOk();

                $client->request('GET', '/lessons/' . $lesson->getId() . '/edit');
                $this->assertResponseOk();

                $client->request('POST', '/lessons/' . $lesson->getId() . '/edit');
                $this->assertResponseOk();
            }
        }
    }

    public function testLessonCreationWithValidFields(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '?????????? ????????',
            'lesson[number]' => 1000,
        ]);

        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));
        $crawler = $client->followRedirect();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $client->click($lessonLink);
        $this->assertResponseOk();
    }

    public function testLessonCreationWithBlankFields(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '',
            'lesson[content]' => '?????????? ????????',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('???????????????? ???? ???????????? ???????? ????????????.', $error->text());

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('???????????????? ???? ???????????? ???????? ????????????.', $error->text());

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '?????????? ????????',
            'lesson[number]' => '',
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('???????????????? ???? ???????????? ???????? ????????????.', $error->text());
    }

    public function testLessonCreationWithInvalidLengthFields(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => 'qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               wertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqw
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq',
            'lesson[content]' => '?????????? ????????',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('???????????????? ?????????????? ??????????????. ???????????? ???????? ?????????? 255 ???????????????? ?????? ????????????.', $error->text());

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '?????????? ????????',
            'lesson[number]' => 1000000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('???????????????? ???????????? ???????? ?????????? 1 ?? 10000.', $error->text());
    }

    public function testLessonsDeleteAfterCourseDelete(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonLinks = $crawler->filter('.list-group-item > a')->link();

        $client->submitForm('course-delete');
        self::assertTrue($client->getResponse()->isRedirect('/courses/'));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        foreach ($lessonLinks as $lessonLink) {
            $client->request('GET', $lessonLink);
            $this->assertResponseNotFound();
        }
    }

    public function testLessonDelete(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $courseLink = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($courseLink);
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $client->submitForm('lesson-delete');
        self::assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        self::assertCount(0, $crawler->filter('.list-group-item'));
    }

    public function testLessonEditForm(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-edit')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $form['lesson[name]'] = '???????????????????? ????????';
        $form['lesson[content]'] = '???????????????????? ????????';
        $form['lesson[number]'] = 9999;
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $courseName = $crawler->filter('.card-header')->text();
        self::assertEquals($course->getName() . ' / ???????????????????? ????????', $courseName);

        $courseDescription = $crawler->filter('.card-text')->text();
        self::assertEquals('???????????????????? ????????', $courseDescription);
    }
}
