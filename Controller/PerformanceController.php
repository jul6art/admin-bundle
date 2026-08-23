<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Jul6Art\CoreBundle\Performance\Service\DashboardViewBuilder;
use Jul6Art\CoreBundle\Performance\Service\PerformanceExporter;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * L'écran du profileur de performance : ce que `jul6art/core-bundle` collecte, rendu lisible.
 *
 * ⚠️ Ce contrôleur n'existe QUE si `core-bundle` est installé — c'est le
 * {@see \Jul6Art\AdminBundle\DependencyInjection\Compiler\PerformanceControllerPass} qui le
 * retire sinon. `admin-bundle` ne requiert pas le core : une application peut prendre la coquille
 * sans la brique de profilage, et une référence non résolvable casserait son conteneur.
 *
 * ⚠️ Les routes ne naissent PAS de ces attributs : l'application les importe où elle veut, ce qui
 * est aussi là qu'elle choisit le pare-feu qui les entoure. Le préfixe de nom `admin_performance_`
 * porté par les trois routes est en revanche un contrat — c'est lui que `core.performance.ignored_route_prefix` exclut de la
 * collecte, faute de quoi le tableau de bord mesurerait sa propre page.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PerformanceController extends AbstractController
{
    public function __construct(
        private readonly PerformanceStoreInterface $store,
        private readonly DashboardViewBuilder $viewBuilder,
        private readonly PerformanceExporter $exporter,
    ) {
    }

    #[Route('/performance', name: 'admin_performance_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dashboard(): Response
    {
        return $this->render('@Admin/performance/dashboard.html.twig', $this->viewBuilder->build());
    }

    #[Route('/performance/export.{format}', name: 'admin_performance_export', requirements: ['format' => 'csv|json'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function export(string $format): StreamedResponse
    {
        // En flux : le store d'une journée de trafic ne tient pas forcément en mémoire, et c'est
        // précisément quand il est gros qu'on veut l'exporter.
        $response = new StreamedResponse(fn () => $this->exporter->stream($this->store->listAll(), $format));
        $response->headers->set('Content-Type', 'csv' === $format ? 'text/csv; charset=utf-8' : 'application/json');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="performance.%s"', $format));

        return $response;
    }

    #[Route('/performance/clear', name: 'admin_performance_clear', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function clear(): RedirectResponse
    {
        $this->store->clear();

        return $this->redirectToRoute('admin_performance_dashboard');
    }
}
