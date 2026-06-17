<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Twig;

use AtlasServices\HermesBookingBundle\Service\BookingDateTimeHelper;
use AtlasServices\HermesBookingBundle\Service\BookingFormVarsProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class BookingExtension
{
    public function __construct(
        private readonly BookingFormVarsProvider $formVarsProvider,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, string> $presentation
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction('booking_form_vars')]
    public function formVars(
        string $bookingKey,
        array $presentation = [],
        ?string $presentationText = null,
        ?string $userText = null,
    ): array {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        return $this->formVarsProvider->provide(
            $bookingKey,
            $locale,
            $presentation,
            $presentationText,
            $userText,
        );
    }

    #[AsTwigFilter('booking_datetime')]
    public function formatBookingDateTime(\DateTimeInterface $value, string $format = 'd/m/Y H:i'): string
    {
        $immutable = $value instanceof \DateTimeImmutable
            ? $value
            : \DateTimeImmutable::createFromInterface($value);

        return $this->dateTimeHelper->fromStorage($immutable)->format($format);
    }
}
