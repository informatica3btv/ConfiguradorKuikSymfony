<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Repository\ProjectRepository;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

class FichaProductPdfGenerator
{
    public function __construct(
      
    ) {
      
    }

    public function generateFromTextAndTwoImages(
        ConfigurationRepository $repo,
        ProjectRepository $repoProject,
        EntityManagerInterface $em
    ) {

    }
}
