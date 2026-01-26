<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ConfigurationRepository;
use App\Repository\ConfigurationTypeRepository;
use App\Repository\ColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ConfigurationController extends AbstractController
{
    /**
     * Devuelve (o crea) la configuración asociada a un proyecto.
     * Asumimos 1 Configuration por Project.
     */
    private function getOrCreateConfigurationForProject(
        Project $project,
        EntityManagerInterface $em,
        ConfigurationRepository $repo
    ): Configuration {
        $config = $repo->findOneBy(['project' => $project]);

        if (!$config) {
            $config = new Configuration();
            $config->setProject($project);
            $em->persist($config);
            $em->flush();
        }

        return $config;
    }

    /**
     * @Route("/", name="configuration_project_new")
     */
    public function newProject(): Response
    {
        return $this->render('configurations/project_new.html.twig');
    }

    /**
     * @Route("/configurator/project/create", name="configuration_project_create", methods={"POST"})
     */
    public function createProject(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $projectName = trim((string) $request->request->get('project_name'));
        $clientName  = trim((string) $request->request->get('client_name'));

        if ($projectName === '' || $clientName === '') {
            $this->addFlash('error', 'Faltan campos obligatorios');
            return $this->redirectToRoute('configuration_project_new');
        }

        $project = new Project();
        $project->setProjectName($projectName);
        $project->setClientName($clientName);
        $project->setPhone($request->request->get('phone'));
        $project->setEmail($request->request->get('email'));
        $project->setCity($request->request->get('city'));
        $project->setAddress($request->request->get('address'));

        // Usuario autenticado
        $project->setUser($this->getUser());

        $em->persist($project);
        $em->flush();

        return $this->redirectToRoute('configuration_type', [
            'project_id' => $project->getId(),
        ]);
    }

    /**
     * @Route("/configuration-type", name="configuration_type", methods={"GET"})
     */
    public function type(
        Request $request,
        ConfigurationRepository $repo,ConfigurationTypeRepository $configurationTypeRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $projectId = $request->query->getInt('project_id');
        if ($projectId <= 0) {
            throw $this->createNotFoundException('Missing project_id');
        }

        $project = $em->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if ($project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $configId = $request->query->getInt('config_id');

        $types = $configurationTypeRepo->findAll();

        if ($configId > 0) {
            // ✅ EDITAR: cargar existente
            $configuration = $repo->find($configId);
            if (!$configuration) {
                throw $this->createNotFoundException('Configuration not found');
            }

            if (!$configuration->getProject() || $configuration->getProject()->getId() !== $project->getId()) {
                throw $this->createAccessDeniedException();
            }
        } else {
            // ✅ NUEVA: crear
            $configuration = new Configuration();
            $configuration->setProject($project);
            $em->persist($configuration);
            $em->flush();
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        return $this->render('configurations/type.html.twig', [
            'project' => $project,
            'configuration' => $configuration,
            'payload' => $payload,
            'types' => $types
        ]);
    }

    /**
     * @Route("/configuracion", name="configuration_columns", methods={"GET"})
     */
    public function configurador(
        Request $request,
        ConfigurationRepository $repo,
        ColorRepository $colorRepo,
        ConfigurationTypeRepository $configurationTypeRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configId  = $request->query->getInt('config_id');
        $projectId = $request->query->getInt('project_id');

        // type solo lo usamos como "hint" cuando vienes desde la pantalla type
        $typeFromQuery = (string) $request->query->get('type', '');

        // 1) Si viene config_id => EDITAR ESA CONFIG sí o sí
        if ($configId > 0) {
            $configuration = $repo->find($configId);
            if (!$configuration) {
                throw $this->createNotFoundException('Configuration not found');
            }

            $project = $configuration->getProject();
            if (!$project || $project->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            // Si además viene project_id, opcionalmente comprueba que coincide
            if ($projectId > 0 && $project->getId() !== $projectId) {
                throw $this->createAccessDeniedException();
            }
        }
        // 2) Si NO viene config_id => crear nueva para ese proyecto
        else {
            if ($projectId <= 0) {
                throw $this->createNotFoundException('Missing project_id');
            }

            $project = $em->getRepository(Project::class)->find($projectId);
            if (!$project) {
                throw $this->createNotFoundException('Project not found');
            }

            if ($project->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            $configuration = new Configuration();
            $configuration->setProject($project);
            $em->persist($configuration);
            $em->flush();

            $configId = $configuration->getId();
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        // ✅ FIX: el type en editar SIEMPRE sale del payload
        // ✅ en nueva, si viene por query y aún no hay payload.type, úsalo
        $type = $payload['type'] ?? '';
        if ($type === '' && $typeFromQuery !== '') {
            $type = $typeFromQuery;
        }

        $coloresPuerta = $colorRepo->findBy(['type' => 'door']);
        $coloresCuerpo = $colorRepo->findBy(['type' => 'body']);

        $availableAttributes = [];
        $attributesGrouped = [];

        if ($type !== '') {

           
            // trae todos los ConfigurationType de esa familia (por si tienes varios)
            $configTypes = $configurationTypeRepo->findBy(['value' => $type], ['id' => 'ASC']);
            
            // Si solo vas a tener 1 por family, también podrías hacer findOneBy(...)
            // $configType = $configurationTypeRepo->findOneBy(['family' => $type]);

            if (!empty($configTypes)) {
                // junta attributes de todos los types
                $tmp = [];

                foreach ($configTypes as $ct) {
                    foreach ($ct->getAttributes() as $attr) {
                        $tmp[$attr->getId()] = $attr; // evita duplicados por id
                    }
                }

                $availableAttributes = array_values($tmp);

                // ✅ opcional: agrupar por AttributesType (para pintar secciones en twig)
                foreach ($availableAttributes as $attr) {
                    $t = $attr->getAttributesType(); // ManyToOne
                    $groupId = $t ? $t->getId() : 0;

                    if (!isset($attributesGrouped[$groupId])) {
                        $attributesGrouped[$groupId] = [
                            'type' => $t,          // entidad AttributesType
                            'attributes' => [],
                        ];
                    }

                    $attributesGrouped[$groupId]['attributes'][] = $attr;
                }
            }
        }
        return $this->render('home.html.twig', [
            'project' => $project,
            'configuration' => $configuration,
            'payload' => $payload,
            'project_id' => $project->getId(),
            'config_id' => $configuration->getId(),
            'type' => $type,
            'coloresCuerpo' => $coloresCuerpo,
            'coloresPuerta' => $coloresPuerta,
            'availableAttributes' => $availableAttributes,
            'attributesGrouped' => $attributesGrouped
        ]);
    }



    /**
     * @Route("/configurations", name="configurations_list", methods={"GET"})
     */
    public function listConfigurations(ConfigurationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $items = $repo->createQueryBuilder('c')
            ->join('c.project', 'p')
            ->where('p.user = :u')
            ->setParameter('u', $this->getUser())
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('configurations/list.html.twig', [
            'configurations' => $items,
        ]);
    }

    /**
     * API: buscar configuraciones por ID o por usuario (nombre/email)
     * @Route("/api/configurations/search", name="api_configurations_search", methods={"GET"})
     */
    public function search(
        Request $request,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $id = $request->query->getInt('id');
        $qUser = trim((string) $request->query->get('user', ''));

        // 1) Buscar por ID exacto
        if ($id > 0) {
            $items = $repo->findBy(['id' => $id], [], 1);
        }
        // 2) Buscar por usuario (nombre o email) via Project->User
        elseif ($qUser !== '') {
            $items = $repo->createQueryBuilder('c')
                ->join('c.project', 'p')
                ->join('p.user', 'u')
                ->where('LOWER(u.name) LIKE :q OR LOWER(u.email) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($qUser).'%')
                ->orderBy('c.updatedAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }
        // 3) Sin filtros: últimas 10
        else {
            $items = $repo->findBy([], ['updatedAt' => 'DESC'], 10);
        }

        $data = array_map(static function (Configuration $c) {
            $p = $c->getProject();
            $u = $p ? $p->getUser() : null;

            return [
                'id' => $c->getId(),
                'projectId' => $p ? $p->getId() : null,
                'projectName' => $p ? $p->getProjectName() : null,
                'clientName' => $p ? $p->getClientName() : null,
                'clientEmail' => $p ? $p->getEmail() : null,

                'payload' => $c->getPayload(),

                'userName' => $u ? $u->getName() : null,
                'userEmail' => $u ? $u->getEmail() : null,
            ];
        }, $items);

        return $this->json(['items' => $data]);
    }

    /**
     * API: listar configuraciones del usuario logueado (sin pasar user_id por query)
     * @Route("/api/configurations", name="api_configurations_list", methods={"GET"})
     */
    public function list(ConfigurationRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $items = $repo->createQueryBuilder('c')
            ->join('c.project', 'p')
            ->where('p.user = :u')
            ->setParameter('u', $this->getUser())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(static function (Configuration $c) {
            $p = $c->getProject();

            return [
                'id' => $c->getId(),
                'project_id' => $p ? $p->getId() : null,
                'project_name' => $p ? $p->getProjectName() : null,
                'payload' => $c->getPayload(),
                'created_at' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $items));
    }

    /**
     * @Route("/api/create-configuration", name="api_configurations_create", methods={"POST"})
     */
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ConfigurationRepository $repo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        if (!is_array($data) || empty($data)) {
            return $this->json(['error' => 'Empty body'], 400);
        }

        // ✅ Requeridos ahora
        if (empty($data['project_id']) || !array_key_exists('payload', $data)) {
            return $this->json([
                'error' => 'Required: project_id, payload',
                'received' => array_keys($data),
            ], 400);
        }

        $projectId = (int) $data['project_id'];
        $configId  = isset($data['configuration_id']) ? (int) $data['configuration_id'] : 0;

        /** @var Project|null $project */
        $project = $em->getRepository(Project::class)->find($projectId);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        // seguridad: el proyecto es del usuario logueado
        if ($project->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Not allowed'], 403);
        }

        // ✅ 1) Si viene configuration_id => actualizar ESA
        if ($configId > 0) {
            $config = $repo->find($configId);
            if (!$config) {
                return $this->json(['error' => 'Configuration not found'], 404);
            }

            // seguridad: debe pertenecer al mismo proyecto
            if (!$config->getProject() || $config->getProject()->getId() !== $project->getId()) {
                return $this->json(['error' => 'Not allowed'], 403);
            }
        }
        // ✅ 2) Si no viene configuration_id => crear nueva
        else {
            $config = new Configuration();
            $config->setProject($project);
            $em->persist($config);
            $em->flush(); // para tener ID
        }

        // ✅ payload
        $payloadRaw = $data['payload'];
        $payloadArr = is_string($payloadRaw)
            ? (json_decode($payloadRaw, true) ?: [])
            : (is_array($payloadRaw) ? $payloadRaw : []);

        // ✅ si quieres mantener addons previos si no vienen en el nuevo payload
        $old = $config->getPayload() ? (json_decode($config->getPayload(), true) ?: []) : [];
        if (isset($old['addons']) && !isset($payloadArr['addons'])) {
            $payloadArr['addons'] = $old['addons'];
        }

        $config->setPayload(json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
        $config->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->render('configurations/complementos.html.twig', [
            'project' => $project,
            'configuration' => $config,
            'payload' => $payloadArr,
            'columns' => $payloadArr['columns'] ?? [],
        ]);
    }


    /**
     * @Route("/configuration/{id}/addons/next", name="configuration_addons_next", methods={"POST"})
     */
    public function addonsNext(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ConfigurationRepository $repo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        // Seguridad: comprobar que pertenece al usuario logueado
        $p = $configuration->getProject();
        if (!$p || $p->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $addonsRaw = (string) $request->request->get('addons_payload', '{}');
        $addons = json_decode($addonsRaw, true);
        if (!is_array($addons)) {
            $addons = [];
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        $payload['addons'] = $addons;

        $configuration->setPayload(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $configuration->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->redirectToRoute('configuration_summary', [
            'id' => $configuration->getId(),
        ]);
    }

    /**
     * @Route("/configuration/{id}/summary", name="configuration_summary", methods={"GET"})
     */
    public function summary(
        int $id,
        ConfigurationRepository $repo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $p = $configuration->getProject();
        if (!$p || $p->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        return $this->render('configurations/summary.html.twig', [
            'project' => $p,
            'configuration' => $configuration,
            'payload' => $payload,
        ]);
    }

    /**
     * Recuperar configuración por código (ID de configuración)
     * @Route("/configuration/recover", name="configuration_recover", methods={"GET","POST"})
     */
    public function recover(Request $request, ConfigurationRepository $repo): Response
    {
        $error = null;
        $config = null;

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code'));

            if (!ctype_digit($code)) {
                $error = 'El código no es válido.';
            } else {
                $config = $repo->find((int) $code);
                if (!$config) {
                    $error = 'No existe ninguna configuración con ese código.';
                } else {
                    // Redirige al configurador (primer paso) usando project_id
                    $p = $config->getProject();
                    if (!$p) {
                        $error = 'La configuración no tiene proyecto asociado.';
                    } else {
                        return $this->redirectToRoute('configuration_type', [
                            'project_id' => $p->getId(),
                        ]);
                    }
                }
            }
        }

        return $this->render('configurations/recover.html.twig', [
            'error' => $error,
        ]);
    }

    /**
     * @Route("/configuration/{id}/pdf", name="configuration_pdf", methods={"GET"})
     */
    public function pdf(
        int $id,
        ConfigurationRepository $repo,EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        $configuration->setStatus(Configuration::STATUS_CLOSED);
        $em->persist($configuration);
        $em->flush();
        
        $configuration->setStatus(Configuration::STATUS_CLOSED);
        
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $project = $configuration->getProject();
        if (!$project || $project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        // Render HTML del PDF (Twig)
        $html = $this->renderView('pdf/configuration_summary.html.twig', [
            'project' => $project,
            'configuration' => $configuration,
            'payload' => $payload,
        ]);

        // Dompdf options
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // soporta acentos
        $options->setIsRemoteEnabled(true); // por si metes imágenes remotas

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('configuracion_%d.pdf', $configuration->getId());

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => (new ResponseHeaderBag())
                    ->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
            ]
        );
    }


     /**
     * @Route("/configuracion/copiar/{id}", name="configuration_copy", methods={"GET"})
     */
    public function copyConfiguration(
        int $id,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $original = $repo->find($id);
        if (!$original) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $project = $original->getProject();
        if (!$project || $project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Crear nueva config clonada
        $copy = new Configuration();
        $copy->setProject($project);

        // Copia del payload tal cual
        $copy->setPayload($original->getPayload());

        // Si tienes status, normalmente al copiar conviene ponerla "abierta"
        if (method_exists($copy, 'setStatus')) {
            $copy->setStatus(0);
        }

        $em->persist($copy);
        $em->flush();

        // ✅ Ir al configurador con TODO precargado (por el payload del nuevo config_id)
        return $this->redirectToRoute('configuration_columns', [
            'project_id' => $project->getId(),
            'config_id'  => $copy->getId(),
        ]);
    }
}
