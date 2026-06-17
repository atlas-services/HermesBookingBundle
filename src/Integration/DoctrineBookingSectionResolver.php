<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Integration;

use AtlasServices\HermesBookingBundle\Contract\BookingSectionResolverInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBookingSectionResolver implements BookingSectionResolverInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        private readonly string $templateCode,
        private readonly string $templateRelation,
        private readonly string $menuRelation,
    ) {
    }

    public function listBookingCalendars(): array
    {
        if (!class_exists($this->entityClass)) {
            return [];
        }

        $alias = 's';
        $templateAlias = 't';
        $menuAlias = 'm';

        $qb = $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias)
            ->innerJoin(sprintf('%s.%s', $alias, $this->templateRelation), $templateAlias)
            ->leftJoin(sprintf('%s.%s', $alias, $this->menuRelation), $menuAlias)
            ->andWhere(sprintf('%s.code = :code', $templateAlias))
            ->setParameter('code', $this->templateCode)
            ->orderBy(sprintf('%s.name', $menuAlias), 'ASC')
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC');

        $sections = [];
        foreach ($qb->getQuery()->getResult() as $section) {
            if (!method_exists($section, 'getId')) {
                continue;
            }

            $label = sprintf('Section #%s', $section->getId());
            if (method_exists($section, 'getMenu')) {
                $menu = $section->getMenu();
                if (null !== $menu && method_exists($menu, 'getName')) {
                    $name = trim((string) $menu->getName());
                    if ($name !== '') {
                        $label = $name;
                    }
                }
            }

            $sectionId = (int) $section->getId();
            $sections[] = [
                'key' => sprintf('s%d', $sectionId),
                'label' => $label,
            ];
        }

        return $sections;
    }
}
