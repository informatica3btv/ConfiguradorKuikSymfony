<?php

namespace App\Repository;

use App\Entity\Door;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Door::class);
    }

    public function findOneDoorBySerieAndPlaceAndSizeAndMethacrylate(string $serie, ?string $place, string $size, bool $methacrylate, ?string $tipo = null, bool $aceroInoxidable = false): ?Door
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.size = :size')
            ->andWhere('p.methacrylate = :methacrylate')
            ->andWhere('p.aceroInoxidable = :aceroInoxidable')
            ->setParameter('serie', $serie)
            ->setParameter('size', $size)
            ->setParameter('methacrylate', $methacrylate)
            ->setParameter('aceroInoxidable', $aceroInoxidable);

        if ($place !== null) {
            $qb->andWhere('p.place = :place')->setParameter('place', $place);
        }

        if ($tipo !== null) {
            $qb->andWhere('p.tipo = :tipo OR p.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
