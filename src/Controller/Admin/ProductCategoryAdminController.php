<?php

namespace App\Controller\Admin;

use App\Entity\ProductCategory;
use App\Form\AdminProductCategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/product-categories", name="admin_product_categories_")
 */
class ProductCategoryAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(ProductCategory::class)->findBy([], ['position' => 'ASC', 'name' => 'ASC']);

        return $this->render('admin/product_categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/new", name="new", methods={"GET","POST"})
     */
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $category = new ProductCategory();

        $form = $this->createForm(AdminProductCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Categoría creada.');
            return $this->redirectToRoute('admin_product_categories_index');
        }

        return $this->render('admin/product_categories/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Crear categoría',
        ]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods={"GET","POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $category = $em->getRepository(ProductCategory::class)->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Categoría no encontrada');
        }

        $form = $this->createForm(AdminProductCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Categoría actualizada.');
            return $this->redirectToRoute('admin_product_categories_index');
        }

        return $this->render('admin/product_categories/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Editar categoría',
        ]);
    }

    /**
     * @Route("/{id}/delete", name="delete", methods={"POST"})
     */
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $category = $em->getRepository(ProductCategory::class)->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Categoría no encontrada');
        }

        if (!$this->isCsrfTokenValid('delete_product_category_'.$category->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Categoría eliminada.');
        return $this->redirectToRoute('admin_product_categories_index');
    }
}
