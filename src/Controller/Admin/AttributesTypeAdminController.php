<?php

namespace App\Controller\Admin;

use App\Entity\Attribute;
use App\Entity\AttributesType;
use App\Form\AttributeType as AttributeFormType;
use App\Form\AttributesTypeType;
use App\Repository\AttributeRepository;
use App\Repository\AttributesTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @Route("/admin/attributes-types", name="admin_attributes_types_")
 */
class AttributesTypeAdminController extends AbstractController
{
    /**
     * LISTADO de tipos
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(AttributesTypeRepository $repo): Response
    {
        return $this->render('admin/attributes_type/index.html.twig', [
            'types' => $repo->findAll(),
        ]);
    }

    /**
     * Vista de edición + lista (si no pasas id, te manda al primero)
     * @Route("/{id}/edit", name="edit", requirements={"id"="\d+"}, methods={"GET","POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $types = $em->getRepository(AttributesType::class)->findAll();

        $type = $em->getRepository(AttributesType::class)->find($id);
        if (!$type) {
            throw $this->createNotFoundException('Tipo no encontrado');
        }

        $form = $this->createForm(AttributesTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_attributes_types_edit', [
                'id' => $type->getId(),
            ]);
        }

        return $this->render('admin/attributes_type/manage.html.twig', [
            'types' => $types,
            'current' => $type,
            'form' => $form->createView(),
        ]);
    }
    
     /**
     * @Route("/{id}/attributes", name="attributes_index", methods={"GET"})
     */
    public function attributesIndex(int $id, EntityManagerInterface $em): Response
    {
        $type = $em->getRepository(AttributesType::class)->find($id);
        if (!$type) {
            throw $this->createNotFoundException('Tipo no encontrado');
        }

        $attributes = $em->getRepository(Attribute::class)->findBy(
            ['attributesType' => $type],
            ['id' => 'DESC']
        );

        return $this->render('admin/attributes_type/attributes_index.html.twig', [
            'type' => $type,
            'attributes' => $attributes,
        ]);
    }

    /**
     * @Route("/{id}/attributes/new", name="attributes_new", methods={"GET","POST"})
     */
    public function attributesNew(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $type = $em->getRepository(AttributesType::class)->find($id);
        if (!$type) {
            throw $this->createNotFoundException('Tipo no encontrado');
        }

        $attr = new Attribute();
        $attr->setAttributesType($type);    

        $configTypes = $em->getRepository(\App\Entity\ConfigurationType::class)->findAll();

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $value = trim((string) $request->request->get('value', ''));
            $description = trim((string) $request->request->get('description', ''));
            


            if ($name === '' || $value === '') {
                $this->addFlash('error', 'Nombre y value son obligatorios.');
            } else {
                $attr->setName($name);
                $attr->setValue($value);
                $attr->setDescription($description ?: null);

                $configTypeIds = (array) $request->request->get('configurationTypes', []);
                $configTypeIds = array_map('intval', $configTypeIds);

                foreach ($configTypeIds as $ctId) {
                    $ct = $em->getRepository(\App\Entity\ConfigurationType::class)->find($ctId);
                    if ($ct) $attr->addConfigurationType($ct);
                }

                $em->persist($attr);
                $em->flush();

                return $this->redirectToRoute('admin_attributes_types_attributes_index', ['id' => $type->getId()]);
            }
        }

        return $this->render('admin/attributes_type/attribute_new.html.twig', [
            'type' => $type,
            'attr' => $attr,
            'configTypes' => $configTypes,
        ]);
    

    }

    /**
 * @Route("/{id}/attributes/{attrId}/edit", name="attributes_edit", methods={"GET","POST"})
 */
public function attributesEdit(int $id, int $attrId, Request $request, EntityManagerInterface $em): Response
{
    $type = $em->getRepository(AttributesType::class)->find($id);
    if (!$type) throw $this->createNotFoundException('Tipo no encontrado');

    $attr = $em->getRepository(Attribute::class)->find($attrId);
    if (!$attr || $attr->getAttributesType()->getId() !== $type->getId()) {
        throw $this->createNotFoundException('Atributo no encontrado');
    }

    $configTypes = $em->getRepository(\App\Entity\ConfigurationType::class)->findAll();

    if ($request->isMethod('POST')) {
        $name = trim((string) $request->request->get('name', ''));
        $value = trim((string) $request->request->get('value', ''));
        $description = trim((string) $request->request->get('description', ''));

        if ($name === '' || $value === '') {
            $this->addFlash('error', 'Nombre y value son obligatorios.');
        } else {
            $attr->setName($name);
            $attr->setValue($value);
            $attr->setDescription($description ?: null);

            $configTypeIds = (array) $request->request->get('configurationTypes', []);
            $configTypeIds = array_map('intval', $configTypeIds);

            foreach ($attr->getConfigurationTypes() as $ct) {
                $attr->removeConfigurationType($ct);
            }

            foreach ($configTypeIds as $ctId) {
                $ct = $em->getRepository(\App\Entity\ConfigurationType::class)->find($ctId);
                if ($ct) $attr->addConfigurationType($ct);
            }

            $em->flush();

            return $this->redirectToRoute('admin_attributes_types_attributes_index', [
                'id' => $type->getId()
            ]);
        }
    }

    return $this->render('admin/attributes_type/attribute_edit.html.twig', [
        'type' => $type,
        'attr' => $attr,
        'configTypes' => $configTypes,
    ]);
}


   /**
     * @Route("/{id}/attributes/{attrId}/delete", name="attributes_delete", methods={"POST"})
     */
    public function attributesDelete(int $id, int $attrId, Request $request, EntityManagerInterface $em): Response
    {
        $type = $em->getRepository(AttributesType::class)->find($id);
        if (!$type) throw $this->createNotFoundException('Tipo no encontrado');

        $attr = $em->getRepository(Attribute::class)->find($attrId);
        if (!$attr || $attr->getAttributesType()->getId() !== $type->getId()) {
            throw $this->createNotFoundException('Atributo no encontrado');
        }

        if ($this->isCsrfTokenValid('del_attr_'.$attr->getId(), (string) $request->request->get('_token'))) {
            $em->remove($attr);
            $em->flush();
        }

        return $this->redirectToRoute('admin_attributes_types_attributes_index', ['id' => $type->getId()]);
    }


    /**
     * @Route("/new", name="new", methods={"GET","POST"})
     */
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $type = new AttributesType();

        $form = $this->createForm(\App\Form\AttributesTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ generar value automático desde name si está vacío
            if (!$type->getValue()) {
                $slugger = new AsciiSlugger();
                $value = strtolower($slugger->slug((string) $type->getName())->toString());
                $type->setValue($value);
            }

            $em->persist($type);
            $em->flush();

            return $this->redirectToRoute('admin_attributes_types_edit', [
                'id' => $type->getId(),
            ]);
        }

        // lista para sidebar
        $types = $em->getRepository(AttributesType::class)->findAll();

        return $this->render('admin/attributes_type/new.html.twig', [
            'types' => $types,
            'form' => $form->createView(),
        ]);
    }
}
