<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Entity\Project;
use App\Repository\ConfigurationRepository;
use App\Repository\ConfigurationTypeRepository;
use App\Repository\ColorRepository;
use App\Repository\ProjectRepository;
use App\Repository\DoorRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;
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
     * @Route("/configuration/home/instalacion", name="configuration_home_instalacion", methods={"GET"})
     */
    public function homeInstalacion(Request $request, EntityManagerInterface $em): Response
    {
        $configId  = $request->query->get('config_id');
        $projectId = $request->query->get('project_id');

        $configuration = $configId ? $em->getRepository(Configuration::class)->find($configId) : null;
        $project       = $projectId ? $em->getRepository(Project::class)->find($projectId) : null;

        if (!$configuration || !$project) {
            throw $this->createNotFoundException('Configuration o Project no encontrados.');
        }

        $options = [
            ['value' => 'empotrado',     'name' => 'Empotrado'],
            ['value' => 'zocalo',         'name' => 'Zócalo'],
            ['value' => 'soporte_suelo', 'name' => 'Soporte a suelo'],
        ];

        return $this->render('configurations/home_instalacion.html.twig', [
            'configuration' => $configuration,
            'project'       => $project,
            'options'         => $options, 
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

        // Vienen por query cuando navegas desde las pantallas previas
        $typeFromQuery        = (string) $request->query->get('type', '');
        $instalacionFromQuery = (string) $request->query->get('instalacion', '');

        /*
        * 1) EDITAR configuración existente
        */
        if ($configId > 0) {
            $configuration = $repo->find($configId);
            if (!$configuration) {
                throw $this->createNotFoundException('Configuration not found');
            }

            $project = $configuration->getProject();
            if (!$project || $project->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            if ($projectId > 0 && $project->getId() !== $projectId) {
                throw $this->createAccessDeniedException();
            }
        }
        /*
        * 2) CREAR nueva configuración
        */
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

        /*
        * Payload actual
        */
        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        /*
        * TYPE: siempre manda el payload en editar,
        * en nueva se acepta el que venga por query
        */
        $type = $payload['type'] ?? '';
        if ($type === '' && $typeFromQuery !== '') {
            $type = $typeFromQuery;
        }

        /*
        * Guardar type + instalacion en payload si vienen por query
        */
        $changed = false;

        if ($type !== '' && (($payload['type'] ?? '') !== $type)) {
            $payload['type'] = $type;
            $changed = true;
        }

        if ($instalacionFromQuery !== '' && (($payload['instalacion'] ?? '') !== $instalacionFromQuery)) {
            $payload['instalacion'] = $instalacionFromQuery;
            $changed = true;
        }

        if ($changed) {
            $configuration->setPayload(json_encode($payload));
            $em->flush();
        }

        /*
        * Colores
        */
        $coloresPuerta = $colorRepo->findBy(['type' => 'door']);
        $coloresCuerpo = $colorRepo->findBy(['type' => 'body']);

        /*
        * Atributos según type
        */
        $availableAttributes = [];
        $attributesGrouped   = [];

        if ($type !== '') {

            $configTypes = $configurationTypeRepo->findBy(
                ['value' => $type],
                ['id' => 'ASC']
            );

            if (!empty($configTypes)) {

                $tmp = [];

                foreach ($configTypes as $ct) {
                    foreach ($ct->getAttributes() as $attr) {
                        $tmp[$attr->getId()] = $attr;
                    }
                }

                $availableAttributes = array_values($tmp);

                foreach ($availableAttributes as $attr) {
                    $t = $attr->getAttributesType();
                    $groupId = $t ? $t->getId() : 0;

                    if (!isset($attributesGrouped[$groupId])) {
                        $attributesGrouped[$groupId] = [
                            'type' => $t,
                            'attributes' => [],
                        ];
                    }

                    $attributesGrouped[$groupId]['attributes'][] = $attr;
                }
            }
        }

        /*
        * Render
        */
        return $this->render('home.html.twig', [
            'project'             => $project,
            'configuration'       => $configuration,
            'payload'             => $payload,
            'project_id'          => $project->getId(),
            'config_id'           => $configuration->getId(),
            'type'                => $type,
            'instalacion'         => $payload['instalacion'] ?? '',
            'coloresCuerpo'       => $coloresCuerpo,
            'coloresPuerta'       => $coloresPuerta,
            'availableAttributes' => $availableAttributes,
            'attributesGrouped'   => $attributesGrouped,
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

        // Clean up addons: remove entries for doors that no longer exist or changed size
        if (isset($payloadArr['addons']) && is_array($payloadArr['addons'])) {
            $oldSizes = $this->getDoorSizesByNumber($old);
            $newSizes = $this->getDoorSizesByNumber($payloadArr);
            $cleanAddons = [];
            foreach ($newSizes as $doorNum => $size) {
                if (!isset($payloadArr['addons'][$doorNum])) {
                    continue;
                }
                // Keep addon only if the door size hasn't changed
                if (isset($oldSizes[$doorNum]) && $oldSizes[$doorNum] !== $size) {
                    continue;
                }
                $cleanAddons[$doorNum] = $payloadArr['addons'][$doorNum];
            }
            $payloadArr['addons'] = $cleanAddons;
        }

        $config->setPayload(json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
        $config->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();
        

        if(isset($payloadArr['instalacion']) && ($payloadArr['instalacion'] == 'empotrado' || $payloadArr['instalacion'] == 'zocalo' )){

            $payloadPrepared = $this->prepareSummaryPayload($payloadArr);
            return $this->render('configurations/summary.html.twig', [
                'project' => $project,
                'configuration' => $config,
                'payload' => $payloadPrepared ,
            ]);
        }

        else if(isset($payloadArr['instalacion']) && $payloadArr['instalacion'] == 'soporte_suelo'){
            return $this->render('configurations/bracket.html.twig', [
                'project' => $project,
                'configuration' => $config,
                'payload' => $payloadArr,
            ]);
        }
        
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
     * @Route("/configurations/save-brackets", name="save_brackets", methods={"POST"})
     */
    public function saveBrackets(
        Request $request,
        EntityManagerInterface $em,
        ConfigurationRepository $configRepo,
        ProjectRepository $projectRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $projectId = (int) $request->request->get('project_id', 0);
        $configId  = (int) $request->request->get('configuration_id', 0);

        $bracketType  = trim((string) $request->request->get('bracketType', ''));
        $bracketColor = trim((string) $request->request->get('bracketColor', ''));

        if ($projectId <= 0 || $configId <= 0) {
            return $this->json(['error' => 'Missing project_id or configuration_id'], 400);
        }

        if ($bracketType === '' || $bracketColor === '') {
            return $this->json(['error' => 'Missing bracketType or bracketColor'], 400);
        }

        // (opcional) validación de valores permitidos
        $allowedTypes  = ['patas', 'brazos'];
        $allowedColors = ['blanco', 'negro', 'plata'];

        if (!in_array($bracketType, $allowedTypes, true)) {
            return $this->json(['error' => 'Invalid bracketType'], 400);
        }
        if (!in_array($bracketColor, $allowedColors, true)) {
            return $this->json(['error' => 'Invalid bracketColor'], 400);
        }

        $project = $projectRepo->find($projectId);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        // seguridad: el proyecto es del usuario logueado
        if ($project->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Not allowed'], 403);
        }

        $config = $configRepo->find($configId);
        if (!$config) {
            return $this->json(['error' => 'Configuration not found'], 404);
        }

        // seguridad: la config debe pertenecer al proyecto
        if (!$config->getProject() || $config->getProject()->getId() !== $project->getId()) {
            return $this->json(['error' => 'Not allowed'], 403);
        }

        // payload actual
        $payloadArr = $config->getPayload()
            ? (json_decode($config->getPayload(), true) ?: [])
            : [];

        // guardamos selección
        $payloadArr['bracketType']  = $bracketType;
        $payloadArr['bracketColor'] = $bracketColor;

        // si quieres, aseguras instalación
        if (!isset($payloadArr['instalacion'])) {
            $payloadArr['instalacion'] = 'soporte_suelo';
        }

        $config->setPayload(json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
        $config->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        $payloadPrepared = $this->prepareSummaryPayload($payloadArr);
        return $this->render('configurations/summary.html.twig', [
            'project' => $project,
            'configuration' => $config,
            'payload' => $payloadPrepared,
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
        /*if (!$p || $p->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }*/

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        $payloadPrepared = $this->prepareSummaryPayload($payload);

        return $this->render('configurations/summary.html.twig', [
            'project' => $p,
            'configuration' => $configuration,
            'payload' => $payloadPrepared,
        ]);
    }

    /**
     * @Route("/btv-debug", name="btv_debug", methods={"GET"})
     */
    public function btvDebug(\App\Service\BtvApiService $btvApi): JsonResponse
    {
        $result = $btvApi->getProductInfo('05828', 1);
        return new JsonResponse([
            'result' => $result,
            'error'  => $result === null ? 'getProductInfo returned null — check var/log/prod.log' : null,
        ]);
    }

    /**
     * @Route("/configuration/{id}/product-table", name="configuration_product_table", methods={"GET"})
     */
    public function productTable(
        int $id,
        ConfigurationRepository $repo,
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        \App\Service\BtvApiService $btvApi
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        $serie     = $payload['fondo'] ?? '';
        $placement = $payload['placement'] ?? '';

        // Count doors per size (exclude screens and mailboxes)
        $sizeCounts = [];
        foreach ($payload['columns'] ?? [] as $col) {
            foreach (['top', 'bottom', 'single'] as $part) {
                foreach ($col[$part]['blocks'] ?? [] as $blk) {
                    $btype = $blk['type'] ?? 'door';
                    if ($btype !== 'screen' && $btype !== 'mailbox') {
                        $size = (string)($blk['h'] ?? '');
                        $sizeCounts[$size] = ($sizeCounts[$size] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($sizeCounts);

        $products    = [];
        $productInfo = [];
        foreach ($sizeCounts as $size => $count) {
            $product = $doorRepo->findOneDoorBySerieAndPlaceAndSize($serie, $placement, $size);
            $products[$size] = $product;
            if ($product) {
                $productInfo[$size] = $btvApi->getProductInfo($product->getReference(), $count);
            }
        }

        // Count groups: each group needs 1 left side + 1 right side
        $groups     = $payload['groups'] ?? [];
        $groupCount = !empty($groups) ? count($groups) : 1;

        $side = $sideRepo->findOneSideBySerieAndPlace($serie,$placement);
        $sizeCounts['lateral'] = $groupCount;
        $products['lateral']   = $side;

        if ($side) {
            $productInfo['lateral'] = $btvApi->getProductInfo($side->getReference(), $groupCount);
        }

        // Calculate roof needs per group:
        // - Each group of N columns needs floor(N/2) roofs of 2 columns + (N%2) roofs of 1 column
        $roofGroups = !empty($groups) ? $groups : (!empty($payload['columns']) ? [$payload['columns']] : []);
        $roofCounts = []; // [numColumns => totalCount]

        foreach ($roofGroups as $groupCols) {
            if (!is_array($groupCols)) {
                continue;
            }
            $numCols = count($groupCols);
            $pairs   = intdiv($numCols, 2);
            $singles = $numCols % 2;

            if ($pairs > 0) {
                $roofCounts[2] = ($roofCounts[2] ?? 0) + $pairs;
            }
            if ($singles > 0) {
                $roofCounts[1] = ($roofCounts[1] ?? 0) + $singles;
            }
        }

        foreach ($roofCounts as $numCols => $count) {
            $key  = 'tejado_' . $numCols;
            $roof = $roofRepo->findOneRoofBySerieAndPlaceAndColumns($serie, $placement, (string) $numCols);
            $sizeCounts[$key] = $count;
            $products[$key]   = $roof;

            if ($roof) {
                $productInfo[$key] = $btvApi->getProductInfo($roof->getReference(), $count);
            }
        }

        return $this->render('configurations/ajax.html.twig', [
            'products'    => $products,
            'productInfo' => $productInfo,
            'sizeCounts'  => $sizeCounts,
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
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);

        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $project = $configuration->getProject();

        if (!$project || $project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $configuration->setStatus(Configuration::STATUS_CLOSED);
        $em->persist($configuration);
        $em->flush();

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        $screenPath = $this->getParameter('kernel.project_dir') . '/public/assets/pantalla.png';
        $screenBase64 = null;

        if (file_exists($screenPath)) {
            $screenBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($screenPath));
        }

        $armBlanco = null;
        $armPlata = null;
        $armNegro = null;

        $armBlancoPath = $this->getParameter('kernel.project_dir') . '/public/assets/brazo_blanco.jpg';
        $armPlataPath  = $this->getParameter('kernel.project_dir') . '/public/assets/brazo_plata.jpg';
        $armNegroPath  = $this->getParameter('kernel.project_dir') . '/public/assets/brazo_negro.jpg';

        if (file_exists($armBlancoPath)) {
            $armBlanco = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($armBlancoPath));
        }

        if (file_exists($armPlataPath)) {
            $armPlata = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($armPlataPath));
        }

        if (file_exists($armNegroPath)) {
            $armNegro = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($armNegroPath));
        }

        $buzonBase64 = null;
        $buzonPath = $this->getParameter('kernel.project_dir') . '/public/assets/buzon_kuik.png';
        if (file_exists($buzonPath)) {
            $buzonBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($buzonPath));
        }

        $legBase64 = null;
        $legPath = $this->getParameter('kernel.project_dir') . '/public/assets/pie_negro.jpg';
        if (file_exists($legPath)) {
            $legBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($legPath));
        }

        $html = $this->renderView('pdf/configuration_summary.html.twig', [
            'project' => $project,
            'configuration' => $configuration,
            'payload' => $payload,
            'public_dir' => $this->getParameter('kernel.project_dir') . '/public',
            'screen_base64' => $screenBase64,
            'arm_blanco' => $armBlanco,
            'arm_plata' => $armPlata,
            'arm_negro' => $armNegro,
            'buzon_base64' => $buzonBase64,
            'leg_base64' => $legBase64,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

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

    /**
     * @Route("/configuracion/aceptar-configuration/{id}", name="configuration_accept", methods={"GET"})
     */
    public function aceptConfiguration(
        int $id,
        ConfigurationRepository $repo,
        ProjectRepository $repoProject,
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

        $configuration = $repo->find($id);
        $configuration->setStatus(Configuration::STATUS_ACCEPTED);

        $project = $repoProject -> find(($configuration->getProject()->getId()));

        $project->setStatus(1);
    
        $em->persist($configuration);
        $em->persist($project);
        $em->flush();

        $this->addFlash('success', 'Configuración aceptada. Proyecto finalizado.');
        
        return $this->redirectToRoute('project_configurations', ['project_id' => $project->getId()]);
    

    }

    /**
     * Returns a map of door_number (string) => size (string) for all real doors in the payload.
     * Screens and mailboxes are excluded (they don't get door numbers).
     */
    private function getDoorSizesByNumber(array $payload): array
    {
        $groups  = $payload['groups'] ?? [];
        $columns = $payload['columns'] ?? [];

        if (empty($groups) && !empty($columns)) {
            $groups = [$columns];
        }

        $sizes   = [];
        $doorNum = 0;

        foreach ($groups as $groupCols) {
            if (!is_array($groupCols)) {
                continue;
            }
            foreach ($groupCols as $col) {
                foreach (['top', 'bottom', 'single'] as $part) {
                    foreach ($col[$part]['blocks'] ?? [] as $blk) {
                        $type = is_array($blk) ? ($blk['type'] ?? 'door') : 'door';
                        if ($type !== 'screen' && $type !== 'mailbox') {
                            $doorNum++;
                            $h = is_array($blk) ? (string)($blk['h'] ?? 0) : (string)$blk;
                            $sizes[(string) $doorNum] = $h;
                        }
                    }
                }
            }
        }

        return $sizes;
    }

    private function countDoorsInPayload(array $payload): int
    {
        return count($this->getDoorSizesByNumber($payload));
    }

    private function prepareSummaryPayload(array $payload): array
    {
        $groups = $payload['groups'] ?? [];
        $columns = $payload['columns'] ?? [];

        if (empty($groups) && !empty($columns)) {
            $groups = [$columns];
        }

        $addons = $payload['addons'] ?? [];

        $globalDoorNumber = 0;
        $globalPlateNumber = 1;

        $preparedGroups = [];

        foreach ($groups as $groupIndex => $groupCols) {
            if (!is_array($groupCols)) {
                $groupCols = [];
            }

            $groupDoorCounter = 0;
            $preparedCols = [];

            foreach ($groupCols as $col) {
                $preparedCol = $col;

                if (!empty($col['single']['blocks']) && is_array($col['single']['blocks'])) {
                    $preparedCol['single']['blocks_prepared'] = [];

                    foreach ($col['single']['blocks'] as $blk) {
                        $preparedBlock = $this->prepareBlock(
                            $blk,
                            $addons,
                            $globalDoorNumber,
                            $groupDoorCounter,
                            $globalPlateNumber
                        );

                        $preparedCol['single']['blocks_prepared'][] = $preparedBlock;
                    }
                } else {
                    $preparedCol['top']['blocks_prepared'] = [];
                    foreach (($col['top']['blocks'] ?? []) as $blk) {
                        $preparedBlock = $this->prepareBlock(
                            $blk,
                            $addons,
                            $globalDoorNumber,
                            $groupDoorCounter,
                            $globalPlateNumber
                        );

                        $preparedCol['top']['blocks_prepared'][] = $preparedBlock;
                    }

                    $preparedCol['bottom']['blocks_prepared'] = [];
                    foreach (($col['bottom']['blocks'] ?? []) as $blk) {
                        $preparedBlock = $this->prepareBlock(
                            $blk,
                            $addons,
                            $globalDoorNumber,
                            $groupDoorCounter,
                            $globalPlateNumber
                        );

                        $preparedCol['bottom']['blocks_prepared'][] = $preparedBlock;
                    }
                }

                $preparedCols[] = $preparedCol;
            }

            $platesUsedByGroup = max(1, (int) ceil($groupDoorCounter / 16));
            $globalPlateNumber += $platesUsedByGroup;

            $preparedGroups[] = $preparedCols;
        }

        $payload['groups'] = $preparedGroups;

        return $payload;
    }
    
    private function prepareBlock(
        $blk,
        array $addons,
        int &$globalDoorNumber,
        int &$groupDoorCounter,
        int $globalPlateNumber
    ): array {
        if (is_array($blk)) {
            $height = (int) ($blk['h'] ?? 0);
            $type = $blk['type'] ?? 'door';
        } else {
            $height = (int) $blk;
            $type = 'door';
            $blk = ['h' => $height, 'type' => $type];
        }

        $isScreen = ($type === 'screen');
        $isMailbox = ($type === 'mailbox');
        $isSpecial = $isScreen || $isMailbox;

        // Pantalla y buzón NO cuentan como puerta
        if ($isSpecial) {
            $blk['doorNumber'] = null;
            $blk['plateNumber'] = null;
            $blk['socket'] = false;
            $blk['usb'] = false;
            $blk['methacrylate'] = false;
            $blk['isScreen'] = $isScreen;
            $blk['isMailbox'] = $isMailbox;

            return $blk;
        }

        // Solo las puertas normales cuentan
        $globalDoorNumber++;
        $groupDoorCounter++;

        // Cada 16 puertas cambia de placa dentro de la agrupación
        $plateNumber = $globalPlateNumber + intdiv($groupDoorCounter - 1, 16);

        // Los addons siguen referenciando la puerta global real
        $sel = $addons[(string) $globalDoorNumber] ?? $addons[$globalDoorNumber] ?? null;

        // IMPORTANTE:
        // doorNumber = contador dentro de la agrupación, ignorando buzones y pantallas
        $blk['doorNumber'] = $groupDoorCounter;
        $blk['plateNumber'] = $plateNumber;

        $blk['socket'] = is_array($sel) ? !empty($sel['socket']) : false;
        $blk['usb'] = is_array($sel) ? !empty($sel['usb']) : false;
        $blk['methacrylate'] = is_array($sel) ? !empty($sel['methacrylate']) : false;

        $blk['isScreen'] = false;
        $blk['isMailbox'] = false;

        return $blk;
    }
}
