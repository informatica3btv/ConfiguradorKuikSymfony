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


    public function findOneBySerieAndPlace(int $serie, string $place): ?Door
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.place = :place')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneDoorBySerieAndPlaceAndSize(string $serie, string $place, string $size): ?Door
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.place = :place')
            ->andWhere('p.size = :size')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->setParameter('size', $size)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
