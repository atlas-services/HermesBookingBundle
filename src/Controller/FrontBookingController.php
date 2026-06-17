<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Controller;

use AtlasServices\HermesBookingBundle\Form\BookingReservationFormType;
use AtlasServices\HermesBookingBundle\Service\BookingAvailabilityService;
use AtlasServices\HermesBookingBundle\Service\BookingDateTimeHelper;
use AtlasServices\HermesBookingBundle\Service\BookingReservationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}', defaults: ['_locale' => 'fr'], requirements: ['_locale' => '[a-z]{2,3}'], priority: 10)]
final class FrontBookingController extends AbstractController
{
    public function __construct(
        private readonly BookingAvailabilityService $availabilityService,
        private readonly BookingReservationManager $reservationManager,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/booking/{bookingKey}/calendar', name: 'hermes_booking_calendar', methods: ['GET'], requirements: ['bookingKey' => '[a-zA-Z0-9_-]+'])]
    public function calendar(string $bookingKey, Request $request): JsonResponse
    {
        $year = $request->query->getInt('year');
        $month = $request->query->getInt('month');
        $tz = $this->dateTimeHelper->timezone();
        $now = new \DateTimeImmutable('now', $tz);

        if ($year <= 0 || $month <= 0) {
            $year = (int) $now->format('Y');
            $month = (int) $now->format('m');
        }

        return new JsonResponse($this->availabilityService->getMonthAvailability($bookingKey, $year, $month));
    }

    #[Route('/booking/{bookingKey}/slots', name: 'hermes_booking_slots', methods: ['GET'], requirements: ['bookingKey' => '[a-zA-Z0-9_-]+'])]
    public function slots(string $bookingKey, Request $request): JsonResponse
    {
        $date = $request->query->getString('date');
        $day = $this->dateTimeHelper->fromDateString($date);
        if (null === $day) {
            return new JsonResponse(['slots' => []], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'slots' => $this->availabilityService->getAvailableSlots($bookingKey, $day),
        ]);
    }

    #[Route('/booking/{bookingKey}/submit', name: 'hermes_booking_submit', methods: ['POST'], requirements: ['bookingKey' => '[a-zA-Z0-9_-]+'])]
    public function submit(string $bookingKey, Request $request): Response
    {
        $redirect = $request->request->getString('_redirect');
        if ('' === $redirect) {
            $redirect = $this->generateUrl('front_home', ['_locale' => $request->getLocale()]);
        }

        $availableDates = $this->availabilityService->getAvailableDates($bookingKey);
        $dateMin = $availableDates[0] ?? '';
        $dateMax = $availableDates !== [] ? $availableDates[array_key_last($availableDates)] : '';

        $form = $this->createForm(BookingReservationFormType::class, null, [
            'booking_key' => $bookingKey,
            'date_min' => $dateMin,
            'date_max' => $dateMax,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateValue = $form->get('date')->getData();
            $date = $dateValue instanceof \DateTimeInterface
                ? $dateValue->format('Y-m-d')
                : trim((string) $dateValue);
            $time = trim((string) $form->get('time')->getData());

            $startsAt = $this->dateTimeHelper->fromFormParts($date, $time);
            if (null === $startsAt || !$this->availabilityService->isSlotAvailable($bookingKey, $startsAt)) {
                $this->addFlash('danger', $this->translator->trans('booking.flash.error', [], 'booking'));

                return $this->redirect($redirect);
            }

            $data = [
                'customerName' => (string) $form->get('customerName')->getData(),
                'email' => (string) $form->get('email')->getData(),
                'phone' => $form->get('phone')->getData(),
                'message' => $form->get('message')->getData(),
                'date' => $date,
                'time' => $time,
                'locale' => $request->getLocale(),
            ];

            try {
                $this->reservationManager->createReservation($bookingKey, $data);
                $this->addFlash('success', $this->translator->trans('booking.flash.success', [], 'booking'));
            } catch (\RuntimeException) {
                $this->addFlash('danger', $this->translator->trans('booking.flash.error', [], 'booking'));
            }

            return $this->redirect($redirect);
        }

        if ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $error) {
                $message = trim($error->getMessage());
                if ($message !== '') {
                    $this->addFlash('danger', $this->translator->trans($message, [], 'booking'));

                    return $this->redirect($redirect);
                }
            }
        }

        $this->addFlash('danger', $this->translator->trans('booking.flash.invalid', [], 'booking'));

        return $this->redirect($redirect);
    }
}
