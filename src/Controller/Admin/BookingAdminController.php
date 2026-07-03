<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Controller\Admin;

use AtlasServices\HermesBookingBundle\Contract\BookingSectionResolverInterface;
use AtlasServices\HermesBookingBundle\Entity\BookingBlockedDate;
use AtlasServices\HermesBookingBundle\Entity\BookingReservation;
use AtlasServices\HermesBookingBundle\Repository\BookingBlockedDateRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingReservationRepository;
use AtlasServices\HermesBookingBundle\Service\BookingAvailabilityService;
use AtlasServices\HermesBookingBundle\Service\BookingDateTimeHelper;
use AtlasServices\HermesBookingBundle\Service\BookingReservationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/booking')]
#[IsGranted('ROLE_ADMIN')]
final class BookingAdminController extends AbstractController
{
    public function __construct(
        private readonly BookingSectionResolverInterface $sectionResolver,
        private readonly BookingReservationRepository $reservationRepository,
        private readonly BookingCalendarRepository $calendarRepository,
        private readonly BookingBlockedDateRepository $blockedDateRepository,
        private readonly BookingAvailabilityService $availabilityService,
        private readonly BookingReservationManager $reservationManager,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'hermes_booking_admin_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $calendars = $this->sectionResolver->listBookingCalendars();
        $bookingKey = trim($request->query->getString('bookingKey', $calendars[0]['key'] ?? ''));

        if ($bookingKey === '' && [] !== $calendars) {
            $bookingKey = $calendars[0]['key'];
        }

        if ($request->isMethod('POST') && $bookingKey !== '') {
            $this->handlePost($request, $bookingKey);

            return $this->redirectToRoute('hermes_booking_admin_index', ['bookingKey' => $bookingKey]);
        }

        $selectedCalendar = null;
        foreach ($calendars as $calendarOption) {
            if ($calendarOption['key'] === $bookingKey) {
                $selectedCalendar = $calendarOption;
                break;
            }
        }
        $calendarLabel = $selectedCalendar['label'] ?? $bookingKey;
        $calendar = $bookingKey !== '' ? $this->calendarRepository->findOrCreate($bookingKey, $calendarLabel) : null;

        return $this->render('@HermesBooking/admin/index.html.twig', [
            'calendars' => $calendars,
            'bookingKey' => $bookingKey,
            'calendar' => $calendar,
            'blockedDates' => $bookingKey !== '' ? $this->blockedDateRepository->findByBookingKeyOrdered($bookingKey) : [],
            'reservations' => $bookingKey !== ''
                ? $this->reservationRepository->findRecent($bookingKey, 100)
                : $this->reservationRepository->findRecent(null, 100),
            'editingReservation' => $this->resolveEditingReservation($request),
        ]);
    }

    #[Route('/section/{sectionId}', name: 'hermes_booking_admin_section', methods: ['GET'])]
    public function sectionLegacy(int $sectionId): Response
    {
        return $this->redirectToRoute('hermes_booking_admin_index', ['bookingKey' => sprintf('s%d', $sectionId)]);
    }

    #[Route('/{bookingKey}/slots', name: 'hermes_booking_admin_slots', methods: ['GET'], requirements: ['bookingKey' => '[a-zA-Z0-9_-]+'])]
    public function slots(string $bookingKey, Request $request): JsonResponse
    {
        $day = $this->dateTimeHelper->fromDateString($request->query->getString('date'));
        if (null === $day) {
            return new JsonResponse(['slots' => []], Response::HTTP_BAD_REQUEST);
        }

        $excludeId = $request->query->getInt('exclude');
        $exclude = $excludeId > 0 ? $excludeId : null;

        return new JsonResponse([
            'slots' => $this->availabilityService->getAvailableSlots($bookingKey, $day, $exclude),
        ]);
    }

    private function handlePost(Request $request, string $bookingKey): void
    {
        $action = $request->request->getString('_action');

        if ('settings' === $action) {
            $calendar = $this->calendarRepository->findOrCreate($bookingKey);
            $calendar
                ->setBlockWeekends($request->request->getBoolean('block_weekends'))
                ->setSlotDurationMinutes($request->request->getInt('slot_duration_minutes', 60))
                ->setWorkStart($this->normalizeTime($request->request->getString('work_start', '09:00')))
                ->setWorkEnd($this->normalizeTime($request->request->getString('work_end', '18:00')))
                ->setHorizonDays($request->request->getInt('horizon_days', 90))
                ->setMaxParticipantsPerSlot($request->request->getInt('max_participants_per_slot', 1));
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('booking.admin.settings_saved', [], 'booking'));

            return;
        }

        if ('block_date' === $action) {
            $dateRaw = $request->request->getString('blocked_date');
            $day = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
            if (false !== $day) {
                $existing = $this->blockedDateRepository->findOneBy([
                    'bookingKey' => $bookingKey,
                    'blockedDate' => $day,
                ]);
                if (!$existing instanceof BookingBlockedDate) {
                    $blocked = (new BookingBlockedDate())
                        ->setBookingKey($bookingKey)
                        ->setBlockedDate($day)
                        ->setLabel($request->request->getString('label') ?: null);
                    $this->entityManager->persist($blocked);
                    $this->entityManager->flush();
                    $this->addFlash('success', $this->translator->trans('booking.admin.date_blocked', [], 'booking'));
                }
            }

            return;
        }

        if ('unblock_date' === $action) {
            $blockedId = $request->request->getInt('blocked_id');
            $blocked = $this->blockedDateRepository->find($blockedId);
            if ($blocked instanceof BookingBlockedDate && $blocked->getBookingKey() === $bookingKey) {
                $this->entityManager->remove($blocked);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('booking.admin.date_unblocked', [], 'booking'));
            }

            return;
        }

        if ('update_reservation' === $action) {
            $reservation = $this->reservationManager->findReservation($request->request->getInt('reservation_id'));
            if (!$reservation instanceof BookingReservation || $reservation->getBookingKey() !== $bookingKey) {
                $this->addFlash('danger', $this->translator->trans('booking.flash.error', [], 'booking'));

                return;
            }

            try {
                $this->reservationManager->updateReservation($reservation, [
                    'customerName' => $request->request->getString('customer_name'),
                    'email' => $request->request->getString('email'),
                    'phone' => $request->request->getString('phone') ?: null,
                    'message' => $request->request->getString('message') ?: null,
                    'date' => $request->request->getString('date'),
                    'time' => $request->request->getString('time'),
                    'locale' => $request->getLocale(),
                ]);
                $this->addFlash('success', $this->translator->trans('booking.admin.reservation_updated', [], 'booking'));
            } catch (\RuntimeException) {
                $this->addFlash('danger', $this->translator->trans('booking.flash.error', [], 'booking'));
            }

            return;
        }

        if ('delete_reservation' === $action) {
            $reservation = $this->reservationManager->findReservation($request->request->getInt('reservation_id'));
            if ($reservation instanceof BookingReservation && $reservation->getBookingKey() === $bookingKey) {
                $this->reservationManager->deleteReservation($reservation);
                $this->addFlash('success', $this->translator->trans('booking.admin.reservation_deleted', [], 'booking'));
            }
        }
    }

    private function resolveEditingReservation(Request $request): ?BookingReservation
    {
        $editId = $request->query->getInt('edit');
        if ($editId <= 0) {
            return null;
        }

        $reservation = $this->reservationManager->findReservation($editId);

        return $reservation instanceof BookingReservation ? $reservation : null;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}/', $value, $matches)) {
            return substr($matches[0], 0, 5);
        }

        return '09:00';
    }
}
