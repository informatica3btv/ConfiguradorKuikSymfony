<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/projects")
 */
class ProjectController extends AbstractController
{
   /**
     * @Route("/list", name="projects_list", methods={"GET"})
     */
    public function list(Request $request, ProjectRepository $projectRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $status = (int) $request->query->get('status', 0);

        $projects = $projectRepo->findBy(
            ['status' => $status],
            ['id' => 'DESC']
        );

        foreach ($projects as $project) {
            $total = $projectRepo->countConfigurationsByProject($project->getId());
            $project->totalConfigurations = $total;
        }

        return $this->render('projects/list.html.twig', [
            'projects' => $projects,
            'currentStatus' => $status,
        ]);
    }


    /**
     * @Route("/projects/{project_id}/configurations", name="project_configurations", methods={"GET"})
     */
    public function listByProject(
        int $project_id,
        ProjectRepository $projectRepo,
        ConfigurationRepository $configRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $project = $projectRepo->find($project_id);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        // Seguridad: que el proyecto sea del usuario
        /*if ($project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }*/

        $configurations = $configRepo->findBy(
            ['project' => $project],
            ['status' => 'DESC', 'id' => 'DESC']
        );
        
        $items = [];
        foreach ($configurations as $c) {
            $payload = [];
            $raw = $c->getPayload();
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $items[] = [
                'configuration' => $c,
                'payload' => $payload,
            ];
        }

        return $this->render('projects/configurations_list.html.twig', [
            'project' => $project,
            'items' => $items,
            'user' => $project->getUser()
        ]);
    }

    /**
     * @Route("/proyecto/rechazar-proyecto/{id}", name="project_reject", methods={"GET"})
     */
    public function rejectProject(
        int $id,
        ProjectRepository $repoProject,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');


        $project = $repoProject->find($id);

        if (!$project || $project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        
        $project->setStatus(Project::STATUS_REJECTED);


        $em->persist($project);
        $em->flush();

        $this->addFlash('error', ' Proyecto rechazado.');
        
        return $this->redirectToRoute('project_configurations', ['project_id' => $project->getId()]);
    

    }

    /**
     * @Route("/{id}/update-info", name="project_update_info", methods={"POST"})
     */
    public function updateInfo(
        int $id,
        Request $request,
        ProjectRepository $projectRepo,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $project = $projectRepo->find($id);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        // seguridad: solo dueño
        if ($project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_project_info_' . $project->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token inválido');
        }

        $project->setClientName($request->request->get('clientName'));
        $project->setEmail($request->request->get('email'));
        $project->setPhone($request->request->get('phone'));
        $project->setCity($request->request->get('city'));
        $project->setAddress($request->request->get('address'));
        $em->flush();

        $this->addFlash('success', 'Datos actualizados correctamente.');

        // vuelve a tu lista de configuraciones del proyecto
        return $this->redirectToRoute('project_configurations', [
            'project_id' => $project->getId()
        ]);
    }

}
