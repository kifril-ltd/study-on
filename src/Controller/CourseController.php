<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\BillingException;
use App\Form\CourseType;
use App\Form\LessonType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/courses')]
class CourseController extends AbstractController
{
    #[Route('/', name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, BillingClient $billingClient): Response
    {
        $billingCourses = $billingClient->getAllCourses(['type' => 'free']);
        $localCourses = $courseRepository->findAllAsArray();

        $billingCourses = $this->array_field_to_key($billingCourses, 'code');
        $localCourses = $this->array_field_to_key($localCourses, 'code');

        if (!$this->getUser()) {
            $freeCourses = [];
            foreach ($localCourses as $code => $course) {
                if (!isset($billingCourses[$code]) || $billingCourses[$code]['type'] === 'free') {
                    $freeCourses[] = [
                        'course' => $course,
                        'billingInfo' => ['type' => $billingCourses[$code]['type']],
                        'transaction' => null
                    ];
                }
            }
            return $this->render('course/index.html.twig', [
                'courses' => $freeCourses,
            ]);
        }

        $apiToken = $this->getUser()->getApiToken();

        $transactions = $billingClient->getTransactions(['type' => 'payment', 'skip_expired' => true], $apiToken);

        $transactions = $this->array_field_to_key($transactions, 'course_code');

        $courses = [];
        foreach ($localCourses as $code => $course) {
            $courses[] = [
                'course' => $course,
                'billingInfo' => $billingCourses[$code],
                'transaction' => $transactions[$code] ?? null
            ];
        }

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    private function array_field_to_key($array, $key)
    {
        $arrayOut = [];
        foreach ($array as $obj) {
            $arrayOut[$obj[$key]] = $obj;
        }
        return $arrayOut;
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CourseRepository $courseRepository): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $courseRepository->add($course);
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/pay', name: 'app_course_pay', methods: ['GET'])]
    public function pay(Course $course, BillingClient $billingClient): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $apiToken = $this->getUser()->getApiToken();

        $payResponse = $billingClient->pay($course->getCode(), $apiToken);

        return $this->redirectToRoute('app_course_index');
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(Course $course, BillingClient $billingClient): Response
    {
        $billingCourse = $billingClient->getCourseByCode($course->getCode());

        if ($billingCourse['type'] === 'free') {
            return $this->render('course/show.html.twig', [
                'course' => $course,
            ]);
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $apiToken = $this->getUser()->getApiToken();
        $transaction = $billingClient->getTransactions(
            ['course_code' => $course->getCode(), 'skip_expired' => true],
            $apiToken
        );
        if ($transaction) {
            return $this->render('course/show.html.twig', [
                'course' => $course,
            ]);
        }
        throw new \Exception('Данный курс вам недоступен!');
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $courseRepository->add($course);
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $courseRepository->remove($course);
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{course}/lessons/add', name: 'app_course_add_lesson', methods: ['GET', 'POST'])]
    public function addLesson(Request $request, LessonRepository $lessonRepository, Course $course): Response
    {
        $lesson = new Lesson();
        $lesson->setCourse($course);
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lessonRepository->add($lesson);
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('lesson/new.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
            'course' => $course,
        ]);
    }
}
