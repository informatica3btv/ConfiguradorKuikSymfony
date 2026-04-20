<?php

namespace App\Repository;

use App\Entity\Bandeja;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BandejaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bandeja::class);
    }

    public function findOneBySerie(string $serie): ?Bandeja
    {
        return $this->findOneBy(['serie' => $serie]);
    }
}
