<?php
// src/Controller/AdminController.php
namespace App\Controller\Admin;

use App\Entity\AttributesType;
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
}
