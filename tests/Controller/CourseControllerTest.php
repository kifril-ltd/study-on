<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Authentication\AuthTest;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;

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

    private function mockBillingClient()
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock($this->serializer)
        );
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

        self::getClient()->request('GET', $url);
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
            if ($course->getCode() === 'PPBIB' || $course->getCode() === 'MSCB' || $course->getCode() === 'CAMPB') {
                $this->assertResponseOk();
            } else {
                $this->assertResponseCode(Response::HTTP_NOT_ACCEPTABLE, $client->getResponse());
            }

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

        self::getClient()->request('POST', '/courses/new');
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            self::getClient()->request('POST', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            self::getClient()->request('POST', '/courses/' . $course->getId() . '/lessons/add');
            $this->assertResponseOk();
        }
    }

    public function testPagesAccessByRole()
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->userAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $client->request('POST', '/courses/new');
        $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            $client->request('POST', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());

            $client->request('POST', '/courses/' . $course->getId() . '/lessons/add');
            $this->assertResponseCode(Response::HTTP_FORBIDDEN, $client->getResponse());
        }
    }


    public function testCoursesCountAuthorizedUser(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');

        self::assertCount(7, $crawler->filter('.course-card'));
    }

    public function testFreeCoursesAccess(): void
    {
        $this->mockBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');

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

    public function testPaidCoursesAccess()
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $paidCoursesCodes = ['PPBI', 'PPBI2', 'MSC', 'CAMP'];

        /** @var CourseRepository $courseRepository */
        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $client = self::getClient();
        foreach ($paidCoursesCodes as $code) {
            $course = $courseRepository->findOneBy(['code' => $code]);
            $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseCode(Response::HTTP_NOT_ACCEPTABLE, $client->getResponse());
        }
    }

    public function testCourseLessonsCount(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->userAuthData, 'json');
        $crawler = $auth->auth($authRequest);

        $client = self::getClient();

        $userCoursesCodes = ['MSC', 'PPBI'];

        /** @var CourseRepository $courseRepository */
        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        foreach ($userCoursesCodes as $code) {
            $course = $courseRepository->findOneBy(['code' => $code]);
            $crawler = $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();

            $actualLessonsCount = \count($course->getLessons());
            self::assertCount($actualLessonsCount, $crawler->filter('.list-group-item'));
        }
    }

    public function testCourseCreationWithValidFields(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

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

        file_put_contents('lpg.html', $crawler->html());

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $actualCoursesCount = \count($courseRepository->findAll());
        self::assertCount($actualCoursesCount, $crawler->filter('.course-card'));
    }

    public function testCourseCreationWithBlankFields(): void
    {
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

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
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

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
        $auth = new AuthTest();
        $auth->setSerializer($this->serializer);

        $authRequest = $this->serializer->serialize($this->adminAuthData, 'json');
        $crawler = $auth->auth($authRequest);

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

        $client->submitForm('course-delete');
        self::assertTrue($client->getResponse()->isRedirect('/courses/'));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);
        $actualCoursesCount = \count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.course-card'));
    }

    public function testCourseEditForm(): void
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
        file_put_contents('qwe.html', $crawler->html());
        $this->assertResponseOk();

        $courseName = $crawler->filter('.card-title')->text();
        self::assertEquals('Измененный курс', $courseName);

        $courseDescription = $crawler->filter('.card-text')->text();
        self::assertEquals('Измененный курс', $courseDescription);
    }
}
