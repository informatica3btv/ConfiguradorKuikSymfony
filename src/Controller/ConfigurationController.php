<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Entity\Project;
use App\Repository\ConfigurationRepository;
use App\Repository\ConfigurationTypeRepository;
use App\Repository\ColorRepository;
use App\Repository\ProjectRepository;
use App\Repository\MailboxRepository;
use App\Service\ConfigurationService;
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
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

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

        if (!$this->isCsrfTokenValid('create_project', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

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

        $payload = $configuration->getDecodedPayload();

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
            'options'       => $options,
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
        EntityManagerInterface $em,
        MailboxRepository $mailboxRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $configId  = $request->query->getInt('config_id');
        $projectId = $request->query->getInt('project_id');

        // Vienen por query cuando navegas desde las pantallas previas
        $typeFromQuery              = (string) $request->query->get('type', '');
        $instalacionFromQuery       = (string) $request->query->get('instalacion', '');
        $agrupacionFromQuery        = $request->query->get('agrupacion_combinada', null);

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
        $payload = $configuration->getDecodedPayload();

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

        if ($agrupacionFromQuery !== null) {
            $agrupacionValue = ($agrupacionFromQuery === '1' || $agrupacionFromQuery === 'true') ? true : false;
            if (($payload['agrupacion_combinada'] ?? null) !== $agrupacionValue) {
                $payload['agrupacion_combinada'] = $agrupacionValue;
                $changed = true;
            }
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
            'project'              => $project,
            'configuration'        => $configuration,
            'payload'              => $payload,
            'project_id'           => $project->getId(),
            'config_id'            => $configuration->getId(),
            'type'                 => $type,
            'instalacion'          => $payload['instalacion'] ?? '',
            'agrupacion_combinada' => $payload['agrupacion_combinada'] ?? false,
            'coloresCuerpo'        => $coloresCuerpo,
            'coloresPuerta'        => $coloresPuerta,
            'availableAttributes'  => $availableAttributes,
            'attributesGrouped'    => $attributesGrouped,
            'mailboxes'            => $mailboxRepo->findBy([], ['reference' => 'ASC']),
            'controles'            => $em->getRepository(\App\Entity\Control::class)->findBy([], ['reference' => 'ASC']),
            'controlActual'        => $payload['control'] ?? null,
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
     * API pública: buscar configuración por ID para la pantalla de recover.
     * Solo devuelve id, projectName y payload. Sin datos de contacto del cliente.
     * @Route("/api/configurations/public-recover", name="api_configurations_public_recover", methods={"GET"})
     */
    public function publicRecover(
        Request $request,
        ConfigurationRepository $repo
    ): JsonResponse {
        $id = $request->query->getInt('id');

        if ($id <= 0) {
            return $this->json(['error' => 'ID inválido'], 400);
        }

        $config = $repo->find($id);

        if (!$config) {
            return $this->json(['items' => []]);
        }

        $p = $config->getProject();

        return $this->json(['items' => [[
            'id'          => $config->getId(),
            'projectName' => $p ? $p->getProjectName() : null,
            'payload'     => $config->getPayload(),
        ]]]);
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

        if (!$this->isCsrfTokenValid('save_configuration', $data['_token'] ?? null)) {
            return $this->json(['error' => 'Token CSRF inválido'], 403);
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
        $old = $config->getDecodedPayload();
        if (isset($old['addons']) && !isset($payloadArr['addons'])) {
            $payloadArr['addons'] = $old['addons'];
        }

        $payloadArr = $this->configService->cleanAddons($payloadArr, $old);

        $config->setPayload(json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
        $config->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();
        

        if(isset($payloadArr['instalacion']) && ($payloadArr['instalacion'] == 'empotrado' || $payloadArr['instalacion'] == 'zocalo' )){

            $payloadPrepared = $this->configService->prepareSummaryPayload($payloadArr);
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

        if (!$this->isCsrfTokenValid('addons_next', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

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

        $payload = $configuration->getDecodedPayload();

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

        if (!$this->isCsrfTokenValid('save_brackets', $request->request->get('_token'))) {
            return $this->json(['error' => 'Token CSRF inválido'], 403);
        }

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
        $payloadArr = $config->getDecodedPayload();

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

        $payloadPrepared = $this->configService->prepareSummaryPayload($payloadArr);
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

        $payload = $configuration->getDecodedPayload();

        $payloadPrepared = $this->configService->prepareSummaryPayload($payload);

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
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $payload = $configuration->getDecodedPayload();

        // If accepted, use the snapshot stored at acceptance time
        if ($configuration->getStatus() === Configuration::STATUS_ACCEPTED && isset($payload['_acceptedProductTable'])) {
            $table = $payload['_acceptedProductTable'];
        } elseif ($configuration->getStatus() === Configuration::STATUS_ACCEPTED) {
            // Accepted but no snapshot (config was closed before snapshot feature existed).
            // Build table ignoring tipo filter so references still resolve despite DB changes,
            // then persist the snapshot so future views don't need to query the DB again.
            $payloadNoTipo = $payload;
            unset($payloadNoTipo['type']);
            $table = $this->configService->buildProductTable($payloadNoTipo);
            $normalizedProducts = [];
            foreach ($table['products'] as $key => $product) {
                $normalizedProducts[$key] = (is_object($product) && method_exists($product, 'getReference'))
                    ? ['reference' => $product->getReference()]
                    : $product;
            }
            $snapshotTable = $table;
            $snapshotTable['products'] = $normalizedProducts;
            $payload['_acceptedProductTable'] = $snapshotTable;
            $configuration->setPayload(json_encode($payload));
            $em->persist($configuration);
            $em->flush();
        } else {
            $table = $this->configService->buildProductTable($payload);
        }

        $table['instalacionPrecio'] = (float) ($payload['_instalacion_precio'] ?? 0);
        $table['instalacionIva']    = (bool)  ($payload['_instalacion_iva']    ?? false);

        return $this->render('configurations/ajax.html.twig', $table);
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

        $payload = $configuration->getDecodedPayload();

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

        $mbGroupBase64 = null;
        $mbGroupUrl = 'https://dbox.btv.es/products/img/medium/10723.jpg';
        try {
            $mbGroupImg = @file_get_contents($mbGroupUrl);
            if ($mbGroupImg !== false) {
                $mbGroupBase64 = 'data:image/jpeg;base64,' . base64_encode($mbGroupImg);
            }
        } catch (\Exception $e) {
            $mbGroupBase64 = null;
        }

        $legBase64 = null;
        $legPath = $this->getParameter('kernel.project_dir') . '/public/assets/pie_negro.jpg';
        if (file_exists($legPath)) {
            $legBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($legPath));
        }

        $logoBase64 = null;
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/assets/Kuik Smart Lockers Azul.png';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $productTable = $this->configService->buildProductTable($payload);

        $html = $this->renderView('pdf/configuration_summary.html.twig', [
            'project' => $project,
            'configuration' => $configuration,
            'payload' => $payload,
            'public_dir' => $this->getParameter('kernel.project_dir') . '/public',
            'screen_base64' => $screenBase64,
            'arm_blanco' => $armBlanco,
            'arm_plata' => $armPlata,
            'arm_negro' => $armNegro,
            'buzon_base64'    => $buzonBase64,
            'mb_group_base64' => $mbGroupBase64,
            'leg_base64' => $legBase64,
            'logo_base64' => $logoBase64,
            'products'    => $productTable['products'],
            'productInfo' => $productTable['productInfo'],
            'sizeCounts'  => $productTable['sizeCounts'],
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

        // Snapshot references and prices into the payload before accepting
        $payloadArr = $configuration->getDecodedPayload() ?? [];
        $productTable = $this->configService->buildProductTable($payloadArr);

        // Doctrine entities don't serialize to JSON — normalize them to plain arrays
        $normalizedProducts = [];
        foreach ($productTable['products'] as $key => $product) {
            if (is_object($product) && method_exists($product, 'getReference')) {
                $normalizedProducts[$key] = ['reference' => $product->getReference()];
            } else {
                $normalizedProducts[$key] = $product; // already a string (placa, buzon_group)
            }
        }
        $productTable['products'] = $normalizedProducts;

        $payloadArr['_acceptedProductTable'] = $productTable;
        $configuration->setPayload(json_encode($payloadArr));

        $configuration->setStatus(Configuration::STATUS_ACCEPTED);

        $project = $repoProject->find($configuration->getProject()->getId());

        $project->setStatus(1);

        $em->persist($configuration);
        $em->persist($project);
        $em->flush();

        $this->addFlash('success', 'Configuración aceptada. Proyecto finalizado.');

        return $this->redirectToRoute('project_configurations', ['project_id' => $project->getId()]);
    }

    /**
     * @Route("/configuracion/screenshot/{id}", name="configuration_screenshot", methods={"POST"})
     */
    public function saveScreenshot(
        int $id,
        Request $request,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            return new JsonResponse(['ok' => false], 404);
        }

        $project = $configuration->getProject();
        if (!$project || $project->getUser() !== $this->getUser()) {
            return new JsonResponse(['ok' => false], 403);
        }

        $data        = json_decode($request->getContent(), true);
        $screenshots = $data['screenshots'] ?? null;

        if (!$screenshots || !is_array($screenshots)) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid screenshots'], 400);
        }

        foreach ($screenshots as $s) {
            if (!is_string($s) || !str_starts_with($s, 'data:image/')) {
                return new JsonResponse(['ok' => false, 'error' => 'Invalid screenshot entry'], 400);
            }
        }

        $payloadArr = $configuration->getDecodedPayload() ?? [];
        $payloadArr['_screenshots'] = $screenshots;
        $configuration->setPayload(json_encode($payloadArr));

        $em->persist($configuration);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @Route("/configuracion/instalacion/{id}", name="configuration_instalacion", methods={"POST"})
     */
    public function saveInstalacion(
        int $id,
        Request $request,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            return new JsonResponse(['ok' => false], 404);
        }

        $project = $configuration->getProject();
        if (!$project || $project->getUser() !== $this->getUser()) {
            return new JsonResponse(['ok' => false], 403);
        }

        $data  = json_decode($request->getContent(), true);
        $precio = isset($data['precio']) ? (float) $data['precio'] : 0.0;
        $iva    = !empty($data['iva']);

        $payloadArr = $configuration->getDecodedPayload() ?? [];
        $payloadArr['_instalacion_precio'] = $precio;
        $payloadArr['_instalacion_iva']    = $iva;
        $configuration->setPayload(json_encode($payloadArr));

        $em->persist($configuration);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

}
