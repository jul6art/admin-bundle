<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller\Security;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AdminBundle\Account\AccountSpaces;
use Jul6Art\AdminBundle\Form\Security\NewPasswordFormType;
use Jul6Art\AdminBundle\Form\Security\ResetPasswordRequestFormType;
use Jul6Art\AuthBundle\Account\PasswordResetEmail;
use Jul6Art\AuthBundle\Account\PasswordResetMailer;
use Jul6Art\AuthBundle\Account\PasswordWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * The forgotten password, in two steps: a request by address, then a form behind a one-time token (ADR-0001,
 * T20 lot 3). On symfonycasts/reset-password-bundle; the password is written and the e-mail sent by auth-bundle's
 * services, the API's too.
 *
 * ⚠️ **The answer never tells whether the address exists**, by its content nor by its duration:
 *
 * - both cases lead to the same confirmation page, with the same text — the helper's exception is swallowed;
 * - **the request never answers faster than `response_floor_ms`** (250 ms). A known address has a token generated,
 *   a row written, an e-mail built; an unknown one, nothing: measured on cegeta, 1.00 ms against 1.52 ms. The
 *   content was silent, the duration answered. A floor states a property a test checks as is, where a decoy's work
 *   "as long as the real one" drifts silently at the first change. The clock starts before the lookup, which
 *   already carries part of the gap; `hrtime()`, a monotonic clock: an NTP adjustment would make `microtime()` go
 *   back, hence a negative `usleep()`.
 */
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    /** The forms' names, whatever their types: `reset_password_request_form[…]`, `new_password_form[…]`. */
    public const string REQUEST_FORM = 'reset_password_request_form';

    public const string FORM = 'new_password_form';

    /**
     * @param class-string<PasswordAuthenticatedUserInterface> $userClass
     * @param class-string<FormTypeInterface<mixed>>           $requestFormType
     * @param class-string<FormTypeInterface<mixed>>           $formType
     */
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly FormFactoryInterface $forms,
        private readonly PasswordWriter $passwords,
        private readonly PasswordResetMailer $mailer,
        private readonly ManagerRegistry $registry,
        private readonly AccountSpaces $spaces,
        private readonly string $userClass,
        private readonly int $responseFloorMilliseconds,
        private readonly string $mailFrom,
        private readonly string $mailTemplate,
        private readonly ?string $mailTextTemplate,
        private readonly string $mailSubject,
        private readonly string $mailTranslationDomain,
        private readonly string $requestFormType = ResetPasswordRequestFormType::class,
        private readonly string $formType = NewPasswordFormType::class,
    ) {
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function request(Request $request): Response
    {
        // Named by the screen, not by the type: a type the application puts in its place (a captcha added) keeps the
        // fields' names.
        $form = $this->forms->createNamed(self::REQUEST_FORM, $this->requestFormType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            return $this->sendEmail(\is_string($email) ? $email : '', $request);
        }

        return $this->render($this->spaces->template($request, 'reset_password_request'), ['form' => $form] + $this->spaces->context($request), new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        ));
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function checkEmail(Request $request): Response
    {
        // A fake token when there is none: the page renders the same, its link's lifetime included (`resetToken`),
        // without telling whether an e-mail left.
        $resetToken = $this->getTokenObjectFromSession();
        if (!$resetToken instanceof ResetPasswordToken) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render($this->spaces->template($request, 'check_email'), ['resetToken' => $resetToken] + $this->spaces->context($request));
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function reset(Request $request, ?string $token = null): Response
    {
        if (null !== $token) {
            // The token goes to the session and leaves the URL: in it, it would leave in the `Referer` of the first
            // external resource the page loads.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute($this->spaces->route($request, 'reset_password_reset'));
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset token in the session.');
        }

        try {
            $account = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'security.reset_password.flash.invalid_token');

            return $this->redirectToRoute($this->spaces->route($request, 'reset_password_request'));
        }

        if (!$account instanceof PasswordAuthenticatedUserInterface) {
            throw new \LogicException(\sprintf('The reset token led to a %s, which has no password.', get_debug_type($account)));
        }

        $form = $this->forms->createNamed(self::FORM, $this->formType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $password = $form->get('plainPassword')->getData();
            $this->passwords->write($account, \is_string($password) ? $password : '');

            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'security.reset_password.flash.changed');

            return $this->redirectToRoute($this->spaces->route($request, 'login'));
        }

        return $this->render($this->spaces->template($request, 'reset_password'), ['form' => $form] + $this->spaces->context($request), new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        ));
    }

    private function sendEmail(string $email, Request $request): RedirectResponse
    {
        $startedAt = hrtime(true);
        $account = $this->registry->getRepository($this->userClass)->findOneBy(['email' => mb_strtolower(trim($email))]);

        // An unknown address is redirected exactly as a known one. The silence is the feature.
        if ($account instanceof PasswordAuthenticatedUserInterface) {
            try {
                $resetToken = $this->resetPasswordHelper->generateResetToken($account);
                $locale = self::localeOf($account);
                $space = $this->spaces->name($request);

                $this->mailer->deliver(new PasswordResetEmail(
                    from: Address::create($this->mailFrom),
                    to: new Address(self::emailOf($account, $email)),
                    subject: $this->mailSubject,
                    translationDomain: $this->mailTranslationDomain,
                    htmlTemplate: $this->mailTemplate,
                    // The space picks the link's route: a customer receives a link to the portal.
                    context: ['resetToken' => $resetToken, 'space' => $space, 'locale' => $locale ?? $request->getLocale()],
                    textTemplate: $this->mailTextTemplate,
                    // In the language of the ACCOUNT, not of the page: a visitor signed out sees the default one, and
                    // the body renders in the transport, maybe in a worker.
                    locale: $locale,
                ));

                $this->setTokenObjectInSession($resetToken);
            } catch (ResetPasswordExceptionInterface) {
                // Too many requests, or one still valid: the same answer, always.
            }
        }

        $this->holdUntilFloor($startedAt);

        return $this->redirectToRoute($this->spaces->route($request, 'reset_password_check_email'));
    }

    private function holdUntilFloor(int $startedAt): void
    {
        $elapsed = intdiv(hrtime(true) - $startedAt, 1_000);
        $floor = $this->responseFloorMilliseconds * 1_000;

        if ($elapsed < $floor) {
            usleep($floor - $elapsed);
        }
    }

    private static function localeOf(object $account): ?string
    {
        if (!method_exists($account, 'getLocale')) {
            return null;
        }

        $locale = $account->getLocale();

        return \is_string($locale) && '' !== $locale ? $locale : null;
    }

    private static function emailOf(object $account, string $typed): string
    {
        $email = method_exists($account, 'getEmail') ? $account->getEmail() : null;

        return \is_string($email) && '' !== $email ? $email : mb_strtolower(trim($typed));
    }
}
