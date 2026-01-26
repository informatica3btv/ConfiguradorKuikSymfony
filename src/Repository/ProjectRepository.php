<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Configuration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Devuelve cuántas configuraciones tiene un proyecto.
     */
    public function countConfigurationsByProject(int $projectId): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Configuration::class, 'c')
            ->where('c.project = :pid')
            ->setParameter('pid', $projectId)
            ->getQuery()
            ->getSingleScalarResult();
    }

     /** Devuelve los proyectos paginados (últimos primero) */
    public function findPaginated(int $page, int $limit = 15): array
    {
        $offset = max(0, ($page - 1) * $limit);

        // Si tienes createdAt úsalo. Si no, cambia a p.id
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')   // <-- si no existe, usa: ->orderBy('p.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Variante por entidad Project.
     */
    public function countConfigurations(Project $project): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Configuration::class, 'c')
            ->where('c.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
}
