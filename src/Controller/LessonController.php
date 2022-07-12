<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\LessonType;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/lessons')]
class LessonController extends AbstractController
{
    #[Route('/{id}', name: 'app_lesson_show', methods: ['GET'])]
    public function show(Lesson $lesson, BillingClient $billingClient): Response
    {
        $course = $lesson->getCourse();

        $billingCourse = $billingClient->getCourseByCode($course->getCode());

        if ($billingCourse['type'] === 'free') {
            return $this->render('lesson/show.html.twig', [
                'lesson' => $lesson,
            ]);
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $apiToken = $this->getUser()->getApiToken();
        $transaction = $billingClient->getTransactions(
            ['type' => 'payment', 'course_code' => $course->getCode(), 'skip_expired' => true],
            $apiToken
        );

        if ($transaction) {
            return $this->render('lesson/show.html.twig', [
                'lesson' => $lesson,
            ]);
        }
        throw new HttpException(Response::HTTP_NOT_ACCEPTABLE,'Данный курс вам недоступен!');
    }

    #[Route('/{id}/edit', name: 'app_lesson_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lesson $lesson, LessonRepository $lessonRepository): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lessonRepository->add($lesson);
            return $this->redirectToRoute(
                'app_course_show',
                ['id' => $lesson->getCourse()->getId()],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->renderForm('lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lesson_delete', methods: ['POST'])]
    public function delete(Request $request, Lesson $lesson, LessonRepository $lessonRepository): Response
    {
        $courseId = $lesson->getCourse()->getId();

        if ($this->isCsrfTokenValid('delete' . $lesson->getId(), $request->request->get('_token'))) {
            $lessonRepository->remove($lesson);
        }

        return $this->redirectToRoute('app_course_show', ['id' => $courseId], Response::HTTP_SEE_OTHER);
    }
}
