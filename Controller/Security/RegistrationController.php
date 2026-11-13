<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller\Security;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AdminBundle\Account\AccountSpaces;
use Jul6Art\AdminBundle\Account\Event\RegistrationFormEvent;
use Jul6Art\AdminBundle\Account\Event\RegistrationSubmittedEvent;
use Jul6Art\AdminBundle\Account\Event\UserRegisteredEvent;
use Jul6Art\AdminBundle\Form\Security\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The public sign-up (ADR-0001, T20 lot 3).
 *
 * - **Closed** (`registration.open: false`, an environment variable most often): the route answers 404 — not 403,
 *   which would confirm it exists. Empty `admin.routes.register` and the sign-in card loses its link too.
 * - **The account arrives inactive** by default: a back-office has someone who validates. `active: true` opens
 *   self-service, knowingly; `active: ~` leaves alone an account that has no such flag.
 * - **What differs from one product to the next listens**: {@see RegistrationFormEvent} before the form is built,
 *   {@see RegistrationSubmittedEvent} before the write, in the same flush, {@see UserRegisteredEvent} after it.
 * - **Every submission counts against the limiter** (`registration.limiter`), valid or not, before anything is
 *   written or sent: a sign-up mails the address it is given, so an unlimited form is a mail cannon.
 * - `sign_in` names the authenticator that signs the new account in at once, where accounts are active from the
 *   start.
 */
final class RegistrationController extends AbstractController
{
    /** The form's name, whatever its type: its fields are `registration_form[…]`. */
    public const string FORM = 'registration_form';

    /**
     * @param class-string<PasswordAuthenticatedUserInterface> $userClass
     * @param class-string<FormTypeInterface<mixed>>           $formType
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly FormFactoryInterface $forms,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TranslatorInterface $translator,
        private readonly AccountSpaces $spaces,
        private readonly string $userClass,
        private readonly bool $open,
        private readonly ?bool $active,
        private readonly string $flash,
        private readonly string $formType = RegistrationFormType::class,
        private readonly ?RateLimiterFactoryInterface $limiter = null,
        private readonly string $limiterMessage = 'security.register.too_many_attempts',
        private readonly ?string $signIn = null,
        private readonly ?Security $security = null,
    ) {
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function __invoke(Request $request): Response
    {
        if (!$this->open) {
            throw new NotFoundHttpException();
        }

        $home = $this->spaces->redirectAuthenticatedTo($request);
        if (null !== $home && $this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute($home);
        }

        $account = new ($this->userClass)();
        $formEvent = $this->dispatcher->dispatch(new RegistrationFormEvent($account, $request));
        // Named by the screen, not by the type: a type the application puts in its place keeps the fields' names
        // (`registration_form[…]`) — its tests, its listeners and its scripts read them.
        $form = $this->forms->createNamed(self::FORM, $this->formType, $account, $formEvent->formOptions());
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        if ($form->isSubmitted() && $this->limiter instanceof RateLimiterFactoryInterface && !$this->limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            $form->addError(new FormError($this->translator->trans($this->limiterMessage, [], 'security')));
            $status = Response::HTTP_TOO_MANY_REQUESTS;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            return $this->register($account, $request, $form);
        }

        return $this->render($this->spaces->template($request, 'register'), ['form' => $form] + $this->spaces->context($request), new Response(status: $status));
    }

    /**
     * @param FormInterface<covariant mixed> $form
     */
    private function register(PasswordAuthenticatedUserInterface $account, Request $request, FormInterface $form): Response
    {
        if (!method_exists($account, 'setPassword')) {
            throw new \LogicException(\sprintf('%s has no setPassword(): admin.account.user_class cannot sign up.', $account::class));
        }

        $plainPassword = method_exists($account, 'getPlainPassword') ? $account->getPlainPassword() : $form->get('plainPassword')->getData();
        $account->setPassword($this->hasher->hashPassword($account, \is_string($plainPassword) ? $plainPassword : ''));
        if (method_exists($account, 'setPlainPassword')) {
            $account->setPlainPassword(null);
        }

        if (null !== $this->active) {
            if (!method_exists($account, 'setIsActive')) {
                throw new \LogicException(\sprintf('%s has no setIsActive(): set admin.account.registration.active to null.', $account::class));
            }

            $account->setIsActive($this->active);
        }

        $this->dispatcher->dispatch(new RegistrationSubmittedEvent($account, $request, $form));

        $manager = $this->registry->getManagerForClass($this->userClass)
            ?? throw new \LogicException(\sprintf('No entity manager maps "%s" (admin.account.user_class).', $this->userClass));
        $manager->persist($account);
        $manager->flush();

        $this->dispatcher->dispatch(new UserRegisteredEvent($account, $request));
        // Signed in at once or not, the account is told it exists: « welcome » on the first page it sees.
        $this->addFlash('success', $this->flash);

        if (null !== $this->signIn && $this->security instanceof Security && $account instanceof UserInterface) {
            $signedIn = $this->security->login($account, $this->signIn);
            $home = $this->spaces->redirectAuthenticatedTo($request);

            return $signedIn ?? $this->redirectToRoute($home ?? $this->spaces->route($request, 'login'));
        }

        return $this->redirectToRoute($this->spaces->route($request, 'login'));
    }
}
