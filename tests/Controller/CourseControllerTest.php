<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Tests\AbstractTest;
use App\Tests\Authentication\AuthTest;
use JMS\Serializer\SerializerInterface;
use function App\Tests\count;

class CourseControllerTest extends AbstractTest
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

    /**
     * @dataProvider urlProviderSuccessful
     */
    public function testMainPagesGetResponseIsSuccessful($url): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();
    }

    public function urlProviderSuccessful()
    {
        yield ['/'];
        yield ['/courses/'];
        yield ['/courses/new'];
    }

    public function testParametrisePagesGetResponseIsSuccessful(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();

            $client->request('GET', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            $client->request('GET', '/courses/' . $course->getId() . '/lessons/add');
            $this->assertResponseOk();
        }
    }

    /**
     * @dataProvider urlProviderNotFound
     */
    public function testPagesResponseIsNotFound($url)
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    public function urlProviderNotFound()
    {
        yield ['/course/'];
        yield ['/courses/-1'];
    }

    public function testPagesPostResponseIsSuccessful(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $client->request('POST', 'courses/new');
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            $client->request('POST', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            $client->request('POST', '/courses/' . $course->getId() . '/lessons/add');
            $this->assertResponseOk();
        }
    }

    public function testCoursesCount(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);
        $actualCoursesCount = count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.course-card'));
    }

    public function testFreeCoursesAccess(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');

        $actualFreeCoursesCount = 3;

        self::assertCount(3, $crawler->filter('.course-card'));

        $freeCoursesCodes = ['PPBIB', 'MSCB', 'CAMPB'];
        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $freeCourses = [];
        foreach ($freeCoursesCodes as $code) {
            $freeCourses[] = $courseRepository->findOneBy(['code' => $code]);
        }

        foreach ($freeCourses as $course) {
            $crawler = $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();
        }
    }

    public function testCourseLessonsCount(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->userAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);

        $userCoursesCodes = [''];

        foreach ($courses as $course) {
            $crawler = $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();

            $actualLessonsCount = count($course->getLessons());
            self::assertCount($actualLessonsCount, $crawler->filter('.list-group-item'));
        }
    }

    public function testCourseCreationWithValidFields(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.add-course-link')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => 'QWERTY',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Курс для теста',
        ]);
        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect('/courses/'));
        $crawler = $client->followRedirect();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $actualCoursesCount = count($courseRepository->findAll());
        self::assertCount($actualCoursesCount, $crawler->filter('.course-card'));
    }

    public function testCourseCreationWithBlankFields(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.add-course-link')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => '',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Курс для теста',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение не должно быть пустым.', $error->text());

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => 'QWERTY',
            'course[name]' => '',
            'course[description]' => 'Курс для теста',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение не должно быть пустым.', $error->text());
    }

    public function testCourseCreationWithInvalidLengthFields(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.add-course-link')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => 'QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQW
                ERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQ
                WERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTY
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Курс для теста',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $form = $submitButton->form([
            'course[code]' => 'QWERTY',
            'course[name]' => 'QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQW
                ERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQ
                WERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTY
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER',
            'course[description]' => 'Курс для теста',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => 'QWERTY',
            'course[name]' => 'Новый курс',
            'course[description]' => 'QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQW
                ERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQ
                WERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTY
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER
                ERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQ
                WERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTY
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER
                ERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQ
                WERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTY
                QWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERT
                YQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWERTYQWER',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение слишком длинное. Должно быть равно 1000 символам или меньше.', $error->text());
    }

    public function testCourseCreationWithNonUniqueCodeField(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.add-course-link')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'course[code]' => 'PPBI',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Курс для теста',
        ]);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Это значение уже используется.', $error->text());
    }

    public function testCourseDelete(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $client->submitForm('course-delete');
        self::assertTrue($client->getResponse()->isRedirect('/courses/'));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);
        $actualCoursesCount = count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.course-card'));
    }

    public function testCourseEditForm(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-edit')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['code' => $form['course[code]']->getValue()]);

        $form['course[code]'] = 'EDITCOURSE';
        $form['course[name]'] = 'Измененный курс';
        $form['course[description]'] = 'Измененный курс';
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $courseName = $crawler->filter('.card-title')->text();
        self::assertEquals('Измененный курс', $courseName);

        $courseDescription = $crawler->filter('.card-text')->text();
        self::assertEquals('Измененный курс', $courseDescription);
    }
}
