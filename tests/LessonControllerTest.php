<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;

class LessonControllerTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    public function testLessonPagesResponseIsSuccessful(): void
    {
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
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Новый урок',
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

    public function testCourseCreationWithBlankFields(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => '',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение не должно быть пустым.', $error->text());

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => '',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение не должно быть пустым.', $error->text());

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '',
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение не должно быть пустым.', $error->text());
    }

    public function testCourseCreationWithInvalidLengthFields(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson-add')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => 'qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               wertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqw
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq
                               qwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyqwertyq',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => 1000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => 1000000,
        ]);
        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.invalid-feedback')->first();
        self::assertEquals('Значение должно быть между 1 и 10000.', $error->text());
    }

    public function testLessonsDeleteAfterCourseDelete(): void
    {
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

    public function testLessonsDelete(): void
    {
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

        self::assertCount(3, $crawler->filter('.list-group-item'));
    }

    public function testLessonEditForm(): void
    {
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

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $form['lesson[name]'] = 'Измененный урок';
        $form['lesson[content]'] = 'Измененный урок';
        $form['lesson[number]'] = 9999;
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect('/courses/' . $course->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $courseName = $crawler->filter('.card-header')->text();
        self::assertEquals($course->getName() . ' / Измененный урок', $courseName);

        $courseDescription = $crawler->filter('.card-text')->text();
        self::assertEquals('Измененный урок', $courseDescription);
    }
}
