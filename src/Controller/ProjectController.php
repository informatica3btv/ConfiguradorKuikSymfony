<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\ConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/projects")
 */
class ProjectController extends AbstractController
{
    /**
     * @Route("/list", name="projects_list", methods={"GET"})
     */
    public function list(ProjectRepository $projectRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $projects = $projectRepo->findBy([], ['id' => 'DESC']);

        foreach($projects as $project){
            
             $total = $projectRepo->countConfigurationsByProject($project->getId());
             $project->totalConfigurations = $total;
        }

        return $this->render('projects/list.html.twig', [
            'projects' => $projects,
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
            ['id' => 'DESC']
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

}
