<?php
// src/Controller/AdminController.php
namespace App\Controller\Admin;

use App\Entity\AttributesType;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function index(EntityManagerInterface $em): Response
    {
        $types = $em->getRepository(AttributesType::class)->findAll();

        return $this->render('admin/dashboard.html.twig', [
            'types' => $types,
        ]);
    }

    /**
     * @Route("/admin/stats", name="admin_stats")
     */
    public function stats(StatsService $statsService): Response
    {
        return $this->render('admin/stats.html.twig', [
            'stats' => $statsService->getStats(),
        ]);
    }
}
