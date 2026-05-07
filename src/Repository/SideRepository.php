<?php

namespace App\Repository;

use App\Entity\Side;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Side::class);
    }

    public function findOneSideBySerieAndPlace(string $serie, ?string $place, ?string $tipo = null, ?string $altura = null): ?Side
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->setParameter('serie', $serie);

        if ($place !== null) {
            $qb->andWhere('p.place = :place')->setParameter('place', $place);
        }

        if ($tipo !== null) {
            $qb->andWhere('p.tipo = :tipo OR p.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        if ($altura !== null) {
            $qb->andWhere('p.altura = :altura OR p.altura IS NULL')
               ->setParameter('altura', $altura);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
