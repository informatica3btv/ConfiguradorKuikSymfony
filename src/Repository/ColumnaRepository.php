<?php

namespace App\Repository;

use App\Entity\Columna;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ColumnaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Columna::class);
    }

    public function findOneColumnaBySerieAndPlace(string $serie, ?string $place, ?string $tipo = null, ?string $altura = null): ?Columna
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.serie = :serie')
            ->setParameter('serie', $serie);

        if ($place !== null) {
            $qb->andWhere('c.place = :place')->setParameter('place', $place);
        }

        if ($tipo !== null) {
            $qb->andWhere('c.tipo = :tipo OR c.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        if ($altura !== null) {
            $qb->andWhere('c.altura = :altura OR c.altura IS NULL')
               ->setParameter('altura', $altura);
        }

        $results = $qb->setMaxResults(1)->getQuery()->getResult();
        return $results[0] ?? null;
    }
}
