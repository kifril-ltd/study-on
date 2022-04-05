<?php

namespace App\Form;

use App\Entity\Lesson;
use App\Form\DataTransformer\CourseToStringTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class LessonType extends AbstractType
{
    private $transformer;

    public function __construct(CourseToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new NotBlank(message: 'Поле не может быть пустым'),
                    new Length(max: 255, maxMessage: 'Название урока должно быть не более 255 символов')
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Контент',
            ])
            ->add('number', NumberType::class, [
                'label' => 'Порядковый номер',
                'constraints' => [
                    new NotBlank(message: 'Поле не может быть пустым'),
                    new Range(
                        notInRangeMessage: 'Значение поля должно быть в пределах от {{ min }} до {{ max }}',
                        min: 1,
                        max: 10000
                    )
                ],
            ])
            ->add('course', HiddenType::class, [
                'data' => $options['course']
            ]);

        $builder->get('course')
            ->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course' => null,
        ]);
    }
}
