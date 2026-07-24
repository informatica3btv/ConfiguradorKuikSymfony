<?php

namespace App\Controller\Admin;

use App\Entity\ProductTypeCategory;
use App\Form\AdminProductTypeCategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/product-types", name="admin_product_types_")
 */
class ProductTypeCategoryAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em): Response
    {
        $types = $em->getRepository(ProductTypeCategory::class)->findBy([], ['label' => 'ASC']);

        return $this->render('admin/product_types/index.html.twig', [
            'types' => $types,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods={"GET","POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $type = $em->getRepository(ProductTypeCategory::class)->find($id);
        if (!$type) {
            throw $this->createNotFoundException('Tipo de producto no encontrado');
        }

        $form = $this->createForm(AdminProductTypeCategoryType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Tipo de producto actualizado.');
            return $this->redirectToRoute('admin_product_types_index');
        }

        return $this->render('admin/product_types/form.html.twig', [
            'form' => $form->createView(),
            'type' => $type,
        ]);
    }
}
