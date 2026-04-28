<?php

namespace App\Repository;

use App\Entity\Control;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ControlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Control::class);
    }

    public function findByPlace(string $place, ?string $tipo = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.place = :place')
            ->setParameter('place', $place);

        if ($tipo !== null) {
            $qb->andWhere('c.tipo = :tipo OR c.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getResult();
    }
}
