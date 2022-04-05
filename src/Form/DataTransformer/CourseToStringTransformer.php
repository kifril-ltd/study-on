<?php

namespace App\Form\DataTransformer;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CourseToStringTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function transform($course): string
    {
        if ($course === null) {
            return '';
        }

        return $course->getId();
    }

    public function reverseTransform($courseId): ?Course
    {
        if (!$courseId) {
            return null;
        }

        $course = $this->entityManager
            ->getRepository(Course::class)
            ->find($courseId)
        ;

        if ($course === null) {
            throw new TransformationFailedException(sprintf(
                'Курс с id "%s" не существует!',
                $courseId
            ));
        }

        return $course;
    }
}
