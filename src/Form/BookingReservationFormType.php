<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Form;

use AtlasServices\HermesBookingBundle\Service\BookingAvailabilityService;
use AtlasServices\HermesBookingBundle\Service\BookingDateTimeHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class BookingReservationFormType extends AbstractType
{
    public function __construct(
        private readonly BookingAvailabilityService $availabilityService,
        private readonly BookingDateTimeHelper $dateTimeHelper,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = (string) $options['input_class'];
        $dateMin = (string) $options['date_min'];
        $dateMax = (string) $options['date_max'];
        $bookingKey = (string) $options['booking_key'];

        $builder
            ->add('customerName', TextType::class, [
                'label' => 'booking.form.name',
                'attr' => ['class' => $inputClass],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'booking.form.email',
                'attr' => ['class' => $inputClass],
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('phone', TelType::class, [
                'label' => 'booking.form.phone',
                'required' => false,
                'attr' => ['class' => $inputClass],
            ])
            ->add('date', TextType::class, [
                'label' => 'booking.form.date',
                'required' => true,
                'attr' => array_filter([
                    'class' => $inputClass,
                    'min' => $dateMin !== '' ? $dateMin : null,
                    'max' => $dateMax !== '' ? $dateMax : null,
                    'data-booking-date' => '1',
                ]),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'booking.form.date_invalid'),
                ],
            ])
            ->add('time', TextType::class, [
                'label' => 'booking.form.time',
                'required' => true,
                'attr' => [
                    'class' => $inputClass,
                    'data-booking-time' => '1',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'booking.form.time_invalid'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'booking.form.message',
                'required' => false,
                'attr' => ['class' => $inputClass, 'rows' => 4],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($bookingKey): void {
            $form = $event->getForm();
            $date = trim((string) $form->get('date')->getData());
            $time = trim((string) $form->get('time')->getData());
            if ($date === '' || $time === '') {
                return;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                return;
            }

            $startsAt = $this->dateTimeHelper->fromFormParts($date, $time);
            if (null === $startsAt) {
                $form->get('time')->addError(new FormError('booking.form.time_invalid'));

                return;
            }

            if (!$this->availabilityService->isSlotAvailable($bookingKey, $startsAt)) {
                $form->get('time')->addError(new FormError('booking.form.time_unavailable'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'booking',
            'input_class' => 'form-control',
            'booking_key' => '',
            'date_min' => '',
            'date_max' => '',
        ]);

        $resolver->setAllowedTypes('input_class', 'string');
        $resolver->setAllowedTypes('booking_key', 'string');
        $resolver->setAllowedTypes('date_min', 'string');
        $resolver->setAllowedTypes('date_max', 'string');
    }
}
