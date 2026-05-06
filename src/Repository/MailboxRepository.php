<?php

namespace App\Repository;

use App\Entity\Mailbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailboxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mailbox::class);
    }

    public function findOneMailboxByDimensions(string $alto, string $ancho, string $fondo, ?string $descripcion = null): ?Mailbox
    {
        return $this->findOneBy(['alto' => $alto, 'ancho' => $ancho, 'fondo' => $fondo, 'descripcion' => $descripcion]);
    }

    public function findAgrupacion(?bool $electronico, ?bool $tarjetero, ?bool $aceroInoxidable): array
    {
        $criteria = ['agrupacion' => false];
        if ($electronico !== null)     { $criteria['electronico']     = $electronico; }
        if ($tarjetero !== null)       { $criteria['tarjetero']       = $tarjetero; }
        if ($aceroInoxidable !== null) { $criteria['aceroInoxidable'] = $aceroInoxidable; }
        return $this->findBy($criteria, ['reference' => 'ASC']);
    }
}
