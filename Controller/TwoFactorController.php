<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Jul6Art\AdminBundle\Form\TwoFactorCodeType;
use Jul6Art\AdminBundle\TwoFactor\TotpEnrolment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An account's own two-factor screen (`admin.two_factor.enabled`): enrol by scanning a QR code and
 * typing a first code; once enabled, turn it off with a current code — unless the application
 * requires it (`admin.two_factor.required`), in which case the screen only says so.
 *
 * ## Wiring
 *
 * Like the appearance screen, the route is imported by the application where it belongs, which is
 * also where the firewall around it is decided:
 *
 * ```yaml
 * # config/routes/admin.yaml
 * admin_two_factor:
 *     resource: '@AdminBundle/Controller/TwoFactorController.php'
 *     type: attribute
 *     prefix: /admin
 * ```
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TwoFactorController extends AbstractController
{
    public const string ROUTE = 'admin_account_two_factor';

    public function __construct(
        private readonly TotpEnrolment $enrolment,
        private readonly FormFactoryInterface $forms,
        private readonly TranslatorInterface $translator,
        private readonly bool $required,
    ) {
    }

    #[Route('/account/two-factor', name: self::ROUTE, methods: ['GET', 'POST'])]
    public function show(Request $request, #[CurrentUser] TwoFactorAwareInterface $account): Response
    {
        $enabled = $account->isTotpAuthenticationEnabled();
        if (!$enabled) {
            $this->enrolment->prepare($account);
        }

        // Activée et imposée : aucun formulaire — il n'y a rien à faire sur cet écran.
        $form = $enabled && $this->required ? null : $this->forms->createNamed($enabled ? 'two_factor_disable' : 'two_factor_enable', TwoFactorCodeType::class);
        $form?->handleRequest($request);

        if ($form instanceof FormInterface && $form->isSubmitted() && $form->isValid()) {
            $code = $form->get('code')->getData();
            $code = \is_string($code) ? $code : '';
            if ($enabled ? $this->enrolment->disable($account, $code) : $this->enrolment->confirm($account, $code)) {
                $this->addFlash('success', $enabled ? 'security.two_factor.flash.disabled' : 'security.two_factor.flash.enabled');

                return $this->redirectToRoute(self::ROUTE);
            }
            // Sur le champ, pas en toast : c'est une erreur de saisie, et elle reste sous les yeux.
            $form->get('code')->addError(new FormError($this->translator->trans('two_factor.code.invalid', [], 'validators')));
        }

        return $this->render('@Admin/account/two_factor.html.twig', [
            'account' => $account,
            'form' => $form,
            'required' => $this->required,
            'qr_code' => $enabled ? null : $this->enrolment->qrCode($account),
            'secret' => $enabled ? null : $this->enrolment->readableSecret($account),
        ], new Response(status: $form?->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
