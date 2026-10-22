<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Jul6Art\AdminBundle\Contract\BackupCodeAwareInterface;
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
 * With `admin.two_factor.backup_codes`, activation also draws ten backup codes, shown ONCE on the
 * page that follows; the screen then counts what is left and redraws a set on a current code. A
 * backup code is accepted wherever a current code is — turning the second factor off after losing
 * the phone is exactly what it is for.
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
 *
 * An application with several connected areas imports it once per area, each under its own
 * prefix, name prefix and layout — the screen then lives inside the area that links to it:
 *
 * ```yaml
 * app_two_factor:
 *     resource: '@AdminBundle/Controller/TwoFactorController.php'
 *     type: attribute
 *     prefix: /app
 *     name_prefix: app_
 *     defaults: { _admin_layout: 'app/layout.html.twig' }
 * ```
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TwoFactorController extends AbstractController
{
    public const string ROUTE = 'admin_account_two_factor';

    /** The route default naming the layout of an area that imported the screen under its own prefix. */
    public const string LAYOUT_ATTRIBUTE = '_admin_layout';

    /** Where the codes just drawn wait for the page after the redirect — the only one that shows them. */
    public const string NEW_BACKUP_CODES = '_admin_two_factor_backup_codes';

    /** At or under this many codes left, the screen says to draw new ones. */
    public const int FEW_BACKUP_CODES = 3;

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
        $backup = $this->enrolment->offersBackupCodes($account);

        // Activée et imposée : pas de désactivation — il ne reste, au plus, que les codes de secours.
        $form = $enabled && $this->required ? null : $this->forms->createNamed($enabled ? 'two_factor_disable' : 'two_factor_enable', TwoFactorCodeType::class, null, ['backup_code' => $enabled && $backup]);
        $backupForm = $enabled && $backup ? $this->forms->createNamed('two_factor_backup_codes', TwoFactorCodeType::class, null, ['backup_code' => true]) : null;
        $form?->handleRequest($request);
        $backupForm?->handleRequest($request);

        // La route qui a servi l'écran, pas `ROUTE` : importé par espace, il y revient.
        $route = $request->attributes->get('_route');
        $route = \is_string($route) ? $route : self::ROUTE;

        if ($form instanceof FormInterface && $form->isSubmitted() && $form->isValid()) {
            $code = self::code($form);
            if ($enabled ? $this->enrolment->disable($account, $code) : $this->enrolment->confirm($account, $code)) {
                $this->addFlash('success', $enabled ? 'security.two_factor.flash.disabled' : 'security.two_factor.flash.enabled');
                if (!$enabled) {
                    $this->keepNewBackupCodes($request, $this->enrolment->generateBackupCodes($account));
                }

                return $this->redirectToRoute($route);
            }
            // Sur le champ, pas en toast : c'est une erreur de saisie, et elle reste sous les yeux.
            $form->get('code')->addError(new FormError($this->translator->trans('two_factor.code.invalid', [], 'validators')));
        }

        if ($backupForm instanceof FormInterface && $backupForm->isSubmitted() && $backupForm->isValid()) {
            $codes = $this->enrolment->regenerateBackupCodes($account, self::code($backupForm));
            if (null !== $codes) {
                $this->addFlash('success', 'security.two_factor.flash.backup_codes');
                $this->keepNewBackupCodes($request, $codes);

                return $this->redirectToRoute($route);
            }
            $backupForm->get('code')->addError(new FormError($this->translator->trans('two_factor.code.invalid', [], 'validators')));
        }

        $layout = $request->attributes->get(self::LAYOUT_ATTRIBUTE);
        $submitted = true === $form?->isSubmitted() || true === $backupForm?->isSubmitted();

        return $this->render('@Admin/account/two_factor.html.twig', [
            'layout' => \is_string($layout) ? $layout : null,
            'account' => $account,
            'form' => $form,
            'required' => $this->required,
            'qr_code' => $enabled ? null : $this->enrolment->qrCode($account),
            'secret' => $enabled ? null : $this->enrolment->readableSecret($account),
            'backup_codes' => $backup,
            'backup_form' => $backupForm,
            'backup_codes_left' => $account instanceof BackupCodeAwareInterface ? $account->countBackupCodes() : 0,
            'few_backup_codes' => self::FEW_BACKUP_CODES,
            'new_backup_codes' => $this->takeNewBackupCodes($request),
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param FormInterface<array{code?: string}|null> $form
     */
    private static function code(FormInterface $form): string
    {
        $code = $form->get('code')->getData();

        return \is_string($code) ? $code : '';
    }

    /**
     * @param list<string> $codes
     */
    private function keepNewBackupCodes(Request $request, array $codes): void
    {
        if ([] !== $codes) {
            $request->getSession()->set(self::NEW_BACKUP_CODES, $codes);
        }
    }

    /**
     * The codes drawn on the previous request, removed as they are read: a reload no longer shows them.
     *
     * @return list<string>
     */
    private function takeNewBackupCodes(Request $request): array
    {
        $codes = $request->hasSession() ? $request->getSession()->remove(self::NEW_BACKUP_CODES) : null;

        return \is_array($codes) ? array_values(array_filter($codes, \is_string(...))) : [];
    }
}
