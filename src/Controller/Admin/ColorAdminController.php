<?php

namespace App\Controller\Admin;

use App\Entity\Color;
use App\Form\AdminColorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/colors", name="admin_colors_")
 */
class ColorAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $type = $request->query->get('type', 'door'); // door|body
        if (!in_array($type, ['door', 'body'], true)) {
            $type = 'door';
        }

        $colors = $em->getRepository(Color::class)->findBy(
            ['type' => $type],
            ['id' => 'DESC']
        );

        return $this->render('admin/colors/index.html.twig', [
            'colors' => $colors,
            'type' => $type,
        ]);
    }

    /**
     * @Route("/new", name="new", methods={"GET","POST"})
     */
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $color = new Color();

        // si vienes con ?type=door/body lo preseleccionamos
        $type = $request->query->get('type');
        if (in_array($type, ['door', 'body'], true)) {
            $color->setType($type);
        }

        $form = $this->createForm(AdminColorType::class, $color);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // normaliza hex
            $color->setHex($this->normalizeHex($color->getHex()));
        
            $em->persist($color);
            $em->flush();

            $this->addFlash('success', 'Color creado.');
            return $this->redirectToRoute('admin_colors_index', ['type' => $color->getType()]);
        }

        return $this->render('admin/colors/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Crear color',
        ]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods={"GET","POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $color = $em->getRepository(Color::class)->find($id);
        if (!$color) {
            throw $this->createNotFoundException('Color no encontrado');
        }

        $form = $this->createForm(AdminColorType::class, $color);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $color->setHex($this->normalizeHex($color->getHex()));
            $em->flush();

            $this->addFlash('success', 'Color actualizado.');
            return $this->redirectToRoute('admin_colors_index', ['type' => $color->getType()]);
        }

        return $this->render('admin/colors/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Editar color',
        ]);
    }

    /**
     * @Route("/{id}/delete", name="delete", methods={"POST"})
     */
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $color = $em->getRepository(Color::class)->find($id);
        if (!$color) {
            throw $this->createNotFoundException('Color no encontrado');
        }

        if (!$this->isCsrfTokenValid('delete_color_'.$color->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $type = $color->getType();
        $em->remove($color);
        $em->flush();

        $this->addFlash('success', 'Color eliminado.');
        return $this->redirectToRoute('admin_colors_index', ['type' => $type]);
    }


    private function normalizeHex(string $hex): string
    {
        $hex = trim($hex);

        // Si viene "000" o "000000", le añadimos # (opcional)
        if ($hex !== '' && $hex[0] !== '#') {
            $hex = '#'.$hex;
        }

        return $hex;
    }
}
