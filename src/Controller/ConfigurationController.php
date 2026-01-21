<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use App\Repository\ColorRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class ConfigurationController extends AbstractController
{
    /**
     * @Route("/", name="configuration_index", methods={"GET"})
     */
    public function type(Request $request, ConfigurationRepository $repo): Response
    {
        $configId = $request->query->get('config_id');
        $configuration = null;
        $payload = [];

        if ($configId) {
            $configuration = $repo->find((int)$configId);
            if ($configuration) {
                $payload = json_decode($configuration->getPayload(), true) ?: [];
            }
        }

        return $this->render('configurations/type.html.twig', [
            'configuration' => $configuration,
            'payload' => $payload,
            'config_id' => $configId,
        ]);
    }

    /**
     * @Route("/configuracion", name="configuration_columns", methods={"GET"})
     */
    public function configurador(Request $request, ConfigurationRepository $repo,ColorRepository $colorRepo): Response
    {
        $configId = $request->query->get('config_id');
        $configuration = null;
        $payload = [];

        if ($configId) {
            $configuration = $repo->find((int)$configId);
            if ($configuration) {
                $payload = json_decode($configuration->getPayload(), true) ?: [];
            }
        }

        $coloresPuerta =$colorRepo->findBy(['type' => 'door']);
        $coloresCuerpo = $colorRepo->findBy(['type' => 'body']);


        $type = $request->query->get('type'); // <-- aquí ya lo recibes
        return $this->render('home.html.twig', [
            'configuration' => $configuration,
            'payload' => $payload,
            'config_id' => $configId,
            'type' => $type,
            'coloresCuerpo' => $coloresCuerpo,
            'coloresPuerta' => $coloresPuerta
        ]);
    }

     /**
     * @Route("/configurations", name="configurations_list", methods={"GET"})
     */
    public function listConfigurations(Request $request, ConfigurationRepository $repo): Response
    {
        $userId = $request->query->getInt('user_id');
        if ($userId <= 0) {
            // puedes cambiar esto por redirect o una página de error bonita si quieres
            return $this->render('configurations/list.html.twig', [
                'error' => 'Missing or invalid user_id',
                'configurations' => [],
                'userId' => $userId,
            ], new Response('', 400));
        }

        $configurations = $repo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);
        $user = $this->getUser();

        return $this->render('configurations/list.html.twig', [
            'user' => $user,
            'configurations' => $configurations,
        ]);
    }

    /**
     * @Route("/api/configurations", name="api_configurations_list", methods={"GET"})
     */
    public function list(Request $request, ConfigurationRepository $repo): JsonResponse
    {
        $userId = $request->query->getInt('user_id');
        if ($userId <= 0) {
            return $this->json(['error' => 'Missing or invalid user_id'], 400);
        }

        $items = $repo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);

        return $this->json(array_map(static function (Configuration $c) {
            return [
                'id' => $c->getId(),
                'user_id' => $c->getUserId(),
                'project_name' => $c->getProjectName(),
                'payload' => $c->getPayload(),
                'created_at' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $items));
    }

    /**
     * @Route("/api/create-configuration", name="api_configurations_create", methods={"POST"})
     */
    public function create(Request $request, EntityManagerInterface $em, ConfigurationRepository $repo): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        if (!is_array($data) || empty($data)) {
            return $this->json(['error' => 'Empty body'], 400);
        }

        if (empty($data['user_id']) || empty($data['project_name']) || !array_key_exists('payload', $data)) {
            return $this->json([
                'error' => 'Required: user_id, project_name, payload',
                'received' => array_keys($data),
            ], 400);
        }

        // ✅ Si viene configuration_id -> UPDATE, si no -> CREATE
        $configId = isset($data['configuration_id']) ? (int)$data['configuration_id'] : 0;

        if ($configId > 0) {
            $config = $repo->find($configId);
            if (!$config) {
                // si no existe, creamos nuevo (o puedes devolver 404)
                $config = new Configuration();
                $config->setUserId((int) $data['user_id']);
            } else {
                // seguridad mínima: que sea del mismo usuario
                if ((int)$config->getUserId() !== (int)$data['user_id']) {
                    return $this->json(['error' => 'Not allowed'], 403);
                }
            }
        } else {
            $config = new Configuration();
            $config->setUserId((int) $data['user_id']);
        }

        $config->setProjectName((string) $data['project_name']);

        $payload = $data['payload'];
        $payloadJson = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);

        // ✅ IMPORTANTE: si ya había addons guardados en payload anterior, no los pierdas
        $old = $config->getPayload() ? (json_decode($config->getPayload(), true) ?: []) : [];
        $new = json_decode($payloadJson, true) ?: [];

        if (isset($old['addons']) && !isset($new['addons'])) {
            $new['addons'] = $old['addons'];
        }

        $payloadJson = json_encode($new, JSON_UNESCAPED_UNICODE);
        $config->setPayload($payloadJson);

        $config->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($config);
        $em->flush();

        $payloadArr = json_decode($payloadJson, true) ?: [];

        return $this->render('configurations/complementos.html.twig', [
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
    ): Response
    {
        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $addonsRaw = (string) $request->request->get('addons_payload', '{}');
        $addons = json_decode($addonsRaw, true);
        if (!is_array($addons)) {
            $addons = [];
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        // ✅ Guardar complementos dentro del payload
        $payload['addons'] = $addons;

        $configuration->setPayload(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $em->flush();

        return $this->redirectToRoute('configuration_summary', [
            'id' => $configuration->getId(),
        ]);
    }

    /**
     * @Route("/configuration/{id}/summary", name="configuration_summary", methods={"GET"})
     */
    public function summary(int $id, ConfigurationRepository $repo): Response
    {
        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $payload = $configuration->getPayload()
            ? (json_decode($configuration->getPayload(), true) ?: [])
            : [];

        return $this->render('configurations/summary.html.twig', [
            'configuration' => $configuration,
            'payload' => $payload,
        ]);
    }


    /**
     * @Route("/configuration/{id}/customer", name="configuration_save_customer", methods={"POST"})
     */
    public function saveCustomer(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ConfigurationRepository $repo
    ): Response {

        $configuration = $repo->find($id);

      
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        if (!$this->isCsrfTokenValid('customer_'.$id, (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $configuration->setClientName($request->request->get('customer_name'));
        $configuration->setClientEmail($request->request->get('customer_email'));
        $configuration->setClientPhone($request->request->get('customer_phone'));
        $configuration->setClientCity($request->request->get('customer_city'));
        $configuration->setClientAddress($request->request->get('customer_address'));

        $configuration->setUpdatedAt(new \DateTime());

        $em->flush();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->redirectToRoute('configurations_list', [
            'user_id' => $user->getId()
        ]);


    }


}
