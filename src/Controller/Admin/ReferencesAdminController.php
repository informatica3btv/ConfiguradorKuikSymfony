<?php

namespace App\Controller\Admin;

use App\Entity\Bandeja;
use App\Entity\Columna;
use App\Entity\Control;
use App\Entity\Door;
use App\Entity\Envolvente;
use App\Entity\Mailbox;
use App\Entity\Roof;
use App\Entity\Side;
use App\Repository\BandejaRepository;
use App\Repository\ColumnaRepository;
use App\Repository\ControlRepository;
use App\Repository\DoorRepository;
use App\Repository\EnvolventeRepository;
use App\Repository\MailboxRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/references", name="admin_references_")
 */
class ReferencesAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        ColumnaRepository $columnaRepo,
        MailboxRepository $mailboxRepo,
        EnvolventeRepository $envolventeRepo,
        ControlRepository $controlRepo,
        BandejaRepository $bandejaRepo
    ): Response {
        return $this->render('admin/references/index.html.twig', [
            'doors'       => $doorRepo->findBy([], ['serie' => 'ASC', 'place' => 'ASC', 'size' => 'ASC']),
            'sides'       => $sideRepo->findBy([], ['serie' => 'ASC', 'place' => 'ASC']),
            'roofs'       => $roofRepo->findBy([], ['serie' => 'ASC', 'place' => 'ASC', 'columns' => 'ASC']),
            'columnas'    => $columnaRepo->findBy([], ['serie' => 'ASC', 'place' => 'ASC']),
            'mailboxes'   => $mailboxRepo->findBy([], ['alto' => 'ASC', 'ancho' => 'ASC']),
            'envolventes' => $envolventeRepo->findBy([], ['tipo' => 'ASC', 'rango' => 'ASC']),
            'controles'   => $controlRepo->findBy([], ['reference' => 'ASC']),
            'bandejas'    => $bandejaRepo->findBy([], ['serie' => 'ASC']),
        ]);
    }

    /**
     * @Route("/bulk-delete", name="bulk_delete", methods={"POST"})
     */
    public function bulkDelete(
        Request $request,
        EntityManagerInterface $em,
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        ColumnaRepository $columnaRepo,
        MailboxRepository $mailboxRepo,
        EnvolventeRepository $envolventeRepo,
        ControlRepository $controlRepo,
        BandejaRepository $bandejaRepo
    ): Response {
        if (!$this->isCsrfTokenValid('bulk_delete_refs', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $type = $request->request->get('type');
        $ids  = array_filter(array_map('intval', (array) $request->request->all()['ids'] ?? []));

        if (empty($ids)) {
            $this->addFlash('error', 'No se ha seleccionado ninguna referencia.');
            return $this->redirectToRoute('admin_references_index', ['tab' => $type]);
        }

        $deleted = 0;

        foreach ($ids as $id) {
            switch ($type) {
                case 'door':       $entity = $doorRepo->find($id); break;
                case 'side':       $entity = $sideRepo->find($id); break;
                case 'roof':       $entity = $roofRepo->find($id); break;
                case 'columna':    $entity = $columnaRepo->find($id); break;
                case 'mailbox':    $entity = $mailboxRepo->find($id); break;
                case 'envolvente': $entity = $envolventeRepo->find($id); break;
                case 'control':    $entity = $controlRepo->find($id); break;
                case 'bandeja':    $entity = $bandejaRepo->find($id); break;
                default:           $entity = null;
            }

            if ($entity) {
                $em->remove($entity);
                $deleted++;
            }
        }

        $em->flush();

        $this->addFlash('success', sprintf('%d referencia(s) eliminada(s).', $deleted));

        return $this->redirectToRoute('admin_references_index', ['tab' => $type]);
    }

    /**
     * @Route("/columna/{id}/delete", name="columna_delete", methods={"POST"})
     */
    public function deleteColumna(int $id, Request $request, ColumnaRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Columna eliminada.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'columna']);
    }

    /**
     * @Route("/door/{id}/delete", name="door_delete", methods={"POST"})
     */
    public function deleteDoor(int $id, Request $request, DoorRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Puerta eliminada.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'door']);
    }

    /**
     * @Route("/side/{id}/delete", name="side_delete", methods={"POST"})
     */
    public function deleteSide(int $id, Request $request, SideRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Lateral eliminado.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'side']);
    }

    /**
     * @Route("/mailbox/{id}/delete", name="mailbox_delete", methods={"POST"})
     */
    public function deleteMailbox(int $id, Request $request, MailboxRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Buzón eliminado.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'mailbox']);
    }

    /**
     * @Route("/mailbox/create", name="mailbox_create", methods={"POST"})
     */
    public function createMailbox(Request $request, EntityManagerInterface $em, MailboxRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_mailbox', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference   = trim((string) $request->request->get('reference'));
        $alto        = trim((string) $request->request->get('alto'));
        $ancho       = trim((string) $request->request->get('ancho'));
        $fondo       = trim((string) $request->request->get('fondo'));
        $descripcion = trim((string) $request->request->get('descripcion')) ?: null;
        $tipo = trim((string) $request->request->get('tipo'));
        $tipoVal = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;

        if ($reference === '' || $alto === '' || $ancho === '' || $fondo === '') {
            $this->addFlash('error', 'Todos los campos son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'mailbox']);
        }

        $existing = $repo->findOneMailboxByDimensions($alto, $ancho, $fondo, $descripcion);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe un buzón con esas dimensiones (ref. %s).', $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'mailbox']);
        }

        $em->persist((new Mailbox())->setReference($reference)->setAlto($alto)->setAncho($ancho)->setFondo($fondo)->setDescripcion($descripcion)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Buzón añadido correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'mailbox']);
    }

    /**
     * @Route("/door/create", name="door_create", methods={"POST"})
     */
    public function createDoor(Request $request, EntityManagerInterface $em, DoorRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_door', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference    = trim((string) $request->request->get('reference'));
        $serie        = trim((string) $request->request->get('serie'));
        $place        = trim((string) $request->request->get('place'));
        $size         = trim((string) $request->request->get('size'));
        $methacrylate = (bool) $request->request->get('methacrylate');
        $tipo = trim((string) $request->request->get('tipo'));
        $tipoVal = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;

        if ($reference === '' || $serie === '' || $place === '' || $size === '') {
            $this->addFlash('error', 'Todos los campos son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'door']);
        }

        $existing = $repo->findOneDoorBySerieAndPlaceAndSizeAndMethacrylate($serie, $place, $size, $methacrylate);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe una puerta con esa combinación (ref. %s).', $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'door']);
        }

        $em->persist((new Door())->setReference($reference)->setSerie($serie)->setPlace($place)->setSize($size)->setMethacrylate($methacrylate)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Puerta añadida correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'door']);
    }

    /**
     * @Route("/side/create", name="side_create", methods={"POST"})
     */
    public function createSide(Request $request, EntityManagerInterface $em, SideRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_side', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference = trim((string) $request->request->get('reference'));
        $serie     = trim((string) $request->request->get('serie'));
        $place     = trim((string) $request->request->get('place'));
        $tipo      = trim((string) $request->request->get('tipo'));
        $tipoVal   = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;
        $alturaRaw = trim((string) $request->request->get('altura'));
        $alturaVal = ($tipoVal === 'home' && $alturaRaw !== '') ? $alturaRaw : null;

        if ($reference === '' || $serie === '' || $place === '') {
            $this->addFlash('error', 'Todos los campos son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'side']);
        }

        $existing = $repo->findOneSideBySerieAndPlace($serie, $place, $tipoVal, $alturaVal);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe un lateral con esa combinación (ref. %s).', $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'side']);
        }

        $em->persist((new Side())->setReference($reference)->setSerie($serie)->setPlace($place)->setAltura($alturaVal)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Lateral añadido correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'side']);
    }

    /**
     * @Route("/roof/create", name="roof_create", methods={"POST"})
     */
    public function createRoof(Request $request, EntityManagerInterface $em, RoofRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_roof', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference = trim((string) $request->request->get('reference'));
        $serie     = trim((string) $request->request->get('serie'));
        $place     = trim((string) $request->request->get('place'));
        $columns   = trim((string) $request->request->get('columns'));
        $tipo = trim((string) $request->request->get('tipo'));
        $tipoVal = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;

        if ($reference === '' || $serie === '' || $place === '' || $columns === '') {
            $this->addFlash('error', 'Todos los campos son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'roof']);
        }

        $existing = $repo->findOneRoofBySerieAndPlaceAndColumns($serie, $place, $columns);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe un tejado con esa combinación (ref. %s).', $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'roof']);
        }

        $em->persist((new Roof())->setReference($reference)->setSerie($serie)->setPlace($place)->setColumns($columns)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Tejado añadido correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'roof']);
    }

    /**
     * @Route("/columna/create", name="columna_create", methods={"POST"})
     */
    public function createColumna(Request $request, EntityManagerInterface $em, ColumnaRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_columna', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference = trim((string) $request->request->get('reference'));
        $serie     = trim((string) $request->request->get('serie'));
        $place     = trim((string) $request->request->get('place'));
        $tipo      = trim((string) $request->request->get('tipo'));
        $tipoVal   = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;
        $alturaRaw = trim((string) $request->request->get('altura'));
        $alturaVal = ($tipoVal === 'home' && $alturaRaw !== '') ? $alturaRaw : null;

        if ($reference === '' || $serie === '' || $place === '') {
            $this->addFlash('error', 'Todos los campos son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'columna']);
        }

        $existing = $repo->findOneColumnaBySerieAndPlace($serie, $place, $tipoVal, $alturaVal);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe una columna con esa combinación (ref. %s).', $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'columna']);
        }

        $em->persist((new Columna())->setReference($reference)->setSerie($serie)->setPlace($place)->setAltura($alturaVal)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Columna añadida correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'columna']);
    }

    /**
     * @Route("/roof/{id}/delete", name="roof_delete", methods={"POST"})
     */
    public function deleteRoof(int $id, Request $request, RoofRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Tejado eliminado.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'roof']);
    }

    /**
     * @Route("/envolvente/create", name="envolvente_create", methods={"POST"})
     */
    public function createEnvolvente(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_envolvente', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference   = trim((string) $request->request->get('reference'));
        $tipo        = trim((string) $request->request->get('tipo'));
        $rango       = trim((string) $request->request->get('rango'));
        $descripcion = trim((string) $request->request->get('descripcion')) ?: null;
        $tipoConfig  = trim((string) $request->request->get('tipoConfig'));
        $tipoConfigVal = in_array($tipoConfig, ['home', 'profesional'], true) ? $tipoConfig : null;

        if ($reference === '' || $tipo === '' || $rango === '') {
            $this->addFlash('error', 'Referencia, tipo y rango son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'envolvente']);
        }

        $em->persist((new Envolvente())->setReference($reference)->setTipo($tipo)->setRango($rango)->setDescripcion($descripcion)->setTipoConfig($tipoConfigVal));
        $em->flush();

        $this->addFlash('success', 'Envolvente añadido correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'envolvente']);
    }

    /**
     * @Route("/bandeja/create", name="bandeja_create", methods={"POST"})
     */
    public function createBandeja(Request $request, EntityManagerInterface $em, BandejaRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_bandeja', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference = trim((string) $request->request->get('reference'));
        $serie     = trim((string) $request->request->get('serie'));
        $tipo = trim((string) $request->request->get('tipo'));
        $tipoVal = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;

        if ($reference === '' || $serie === '') {
            $this->addFlash('error', 'Referencia y serie son obligatorias.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'bandeja']);
        }

        $existing = $repo->findOneBySerie($serie);
        if ($existing) {
            $this->addFlash('error', sprintf('Ya existe una bandeja para la serie %s (ref. %s).', $serie, $existing->getReference()));
            return $this->redirectToRoute('admin_references_index', ['tab' => 'bandeja']);
        }

        $em->persist((new Bandeja())->setReference($reference)->setSerie($serie)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Bandeja añadida correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'bandeja']);
    }

    /**
     * @Route("/bandeja/{id}/delete", name="bandeja_delete", methods={"POST"})
     */
    public function deleteBandeja(int $id, Request $request, BandejaRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Bandeja eliminada.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'bandeja']);
    }

    /**
     * @Route("/control/create", name="control_create", methods={"POST"})
     */
    public function createControl(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('create_ref_control', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $reference   = trim((string) $request->request->get('reference'));
        $place       = trim((string) $request->request->get('place'));
        $descripcion = trim((string) $request->request->get('descripcion')) ?: null;
        $tipo = trim((string) $request->request->get('tipo'));
        $tipoVal = in_array($tipo, ['home', 'profesional'], true) ? $tipo : null;

        if ($reference === '' || $place === '') {
            $this->addFlash('error', 'La referencia y el lugar son obligatorios.');
            return $this->redirectToRoute('admin_references_index', ['tab' => 'control']);
        }

        $em->persist((new Control())->setReference($reference)->setPlace($place)->setDescripcion($descripcion)->setTipo($tipoVal));
        $em->flush();

        $this->addFlash('success', 'Control añadido correctamente.');
        return $this->redirectToRoute('admin_references_index', ['tab' => 'control']);
    }

    /**
     * @Route("/control/{id}/delete", name="control_delete", methods={"POST"})
     */
    public function deleteControl(int $id, Request $request, ControlRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Control eliminado.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'control']);
    }

    /**
     * @Route("/envolvente/{id}/delete", name="envolvente_delete", methods={"POST"})
     */
    public function deleteEnvolvente(int $id, Request $request, EnvolventeRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_ref_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $entity = $repo->find($id);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Envolvente eliminado.');
        }

        return $this->redirectToRoute('admin_references_index', ['tab' => 'envolvente']);
    }
}
