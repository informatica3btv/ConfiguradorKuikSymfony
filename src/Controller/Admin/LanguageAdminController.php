<?php

namespace App\Controller\Admin;

use App\Entity\Language;
use App\Form\AdminLanguageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @Route("/admin/languages", name="admin_languages_")
 */
class LanguageAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em): Response
    {
        $languages = $em->getRepository(Language::class)->findBy([], ['position' => 'ASC', 'name' => 'ASC']);

        return $this->render('admin/languages/index.html.twig', [
            'languages' => $languages,
        ]);
    }

    /**
     * @Route("/new", name="new", methods={"GET","POST"})
     */
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $language = new Language();
        if (!$em->getRepository(Language::class)->findOneBy([])) {
            // primer idioma del sistema: por defecto y activo
            $language->setIsDefault(true);
            $language->setIsActive(true);
        }

        $form = $this->createForm(AdminLanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleFlagUpload($form, $language, $slugger);
            $this->applyDefaultLanguageRule($em, $language);

            $em->persist($language);
            $em->flush();

            $this->addFlash('success', 'Idioma creado.');
            return $this->redirectToRoute('admin_languages_index');
        }

        return $this->render('admin/languages/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Crear idioma',
            'language' => $language,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods={"GET","POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $language = $em->getRepository(Language::class)->find($id);
        if (!$language) {
            throw $this->createNotFoundException('Idioma no encontrado');
        }

        $wasDefault = $language->isDefault();

        $form = $this->createForm(AdminLanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasDefault && !$language->isDefault()) {
                $this->addFlash('error', 'No se puede quitar el idioma por defecto directamente; marca otro idioma como por defecto.');
                $language->setIsDefault(true);
            } elseif ($language->isDefault() && !$language->isActive()) {
                $this->addFlash('error', 'El idioma por defecto no se puede desactivar.');
                $language->setIsActive(true);
            }

            $this->handleFlagUpload($form, $language, $slugger);
            $this->applyDefaultLanguageRule($em, $language);

            $em->flush();

            $this->addFlash('success', 'Idioma actualizado.');
            return $this->redirectToRoute('admin_languages_index');
        }

        return $this->render('admin/languages/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Editar idioma',
            'language' => $language,
        ]);
    }

    /**
     * @Route("/{id}/delete", name="delete", methods={"POST"})
     */
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $language = $em->getRepository(Language::class)->find($id);
        if (!$language) {
            throw $this->createNotFoundException('Idioma no encontrado');
        }

        if (!$this->isCsrfTokenValid('delete_language_'.$language->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($language->isDefault()) {
            $this->addFlash('error', 'No se puede eliminar el idioma por defecto.');
            return $this->redirectToRoute('admin_languages_index');
        }

        $this->deleteFlagFile($language);

        $em->remove($language);
        $em->flush();

        $this->addFlash('success', 'Idioma eliminado.');
        return $this->redirectToRoute('admin_languages_index');
    }

    private function applyDefaultLanguageRule(EntityManagerInterface $em, Language $language): void
    {
        if (!$language->isDefault()) {
            return;
        }

        foreach ($em->getRepository(Language::class)->findBy(['isDefault' => true]) as $other) {
            if ($other !== $language) {
                $other->setIsDefault(false);
            }
        }
    }

    private function handleFlagUpload(FormInterface $form, Language $language, SluggerInterface $slugger): void
    {
        /** @var UploadedFile|null $flagFile */
        $flagFile = $form->get('flagFile')->getData();
        if (!$flagFile) {
            return;
        }

        $originalFilename = pathinfo($flagFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$flagFile->guessExtension();

        try {
            $flagFile->move($this->getFlagsDirectory(), $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'No se pudo subir la imagen de la bandera.');
            return;
        }

        $this->deleteFlagFile($language);
        $language->setFlag($newFilename);
    }

    private function deleteFlagFile(Language $language): void
    {
        if (!$language->getFlag()) {
            return;
        }

        $path = $this->getFlagsDirectory().'/'.$language->getFlag();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function getFlagsDirectory(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/flags';
    }
}
