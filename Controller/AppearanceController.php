<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Contract\AppearanceAwareInterface;
use Jul6Art\AdminBundle\Form\AppearanceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The one screen this bundle serves itself: an account editing its own appearance.
 *
 * It is a controller and not an example because the alternative is every application writing the
 * same twelve lines, and one of them forgetting that the form binds an entity Doctrine is already
 * tracking — so a `flush()` is all it takes, and a `persist()` would be a bug.
 *
 * ## Wiring
 *
 * The route is **not** declared here for the application. Import it where it belongs in your URL
 * map, which is also where the surrounding firewall is decided:
 *
 * ```yaml
 * # config/routes/admin.yaml
 * admin_appearance:
 *     resource: '@AdminBundle/Controller/AppearanceController.php'
 *     type: attribute
 *     prefix: /admin
 * ```
 *
 * `IS_AUTHENTICATED_FULLY` is the only guard: appearance is a personal preference, not a
 * permission. An application that wants it behind more says so in `access_control`.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AppearanceController extends AbstractController
{
    #[Route('/account/appearance', name: 'admin_account_appearance_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser]
        AppearanceAwareInterface $user,
    ): Response {
        $form = $this->createForm(AppearanceType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Pas de `persist()` : l'entité vient du token de sécurité, donc elle est déjà suivie
            // par l'unité de travail. Un `persist()` sur une entité gérée est inoffensif, mais il
            // dit le contraire de ce qui se passe.
            $entityManager->flush();

            $this->addFlash('success', 'appearance.flash.saved');

            return new RedirectResponse($request->getUri());
        }

        return $this->render('@Admin/account/appearance.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
