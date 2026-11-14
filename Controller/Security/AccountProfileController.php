<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller\Security;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Jul6Art\AdminBundle\Account\AccountSpaces;
use Jul6Art\AdminBundle\Form\Security\ProfileFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * The profile of the signed-in account: the names it is shown by and its picture (ADR-0001, T20 lot 3).
 *
 * No permission code by default (`profile.access: IS_AUTHENTICATED_FULLY`): an account always manages its own. It is
 * also why the form touches neither the address nor the roles.
 *
 * - **The picture's name is rebuilt**, never the client's: an original name carries the path and extension the
 *   sender chose, and `guessExtension()` reads the content.
 * - **Back to the account's page** after saving when the space names it (its route `profile_show`) and the account
 *   may read it (`profile.show_attribute`): that is where the result shows, picture included. Without that guard,
 *   half the accounts would land on a 403 after a SUCCESSFUL save. A space without such a page — a customer area —
 *   declares none, or an empty one. Otherwise back to the profile.
 */
final class AccountProfileController extends AbstractController
{
    /** The form's name, whatever its type: its fields are `profile_form[…]`. */
    public const string FORM = 'profile_form';

    /**
     * @param class-string                           $userClass
     * @param class-string<FormTypeInterface<mixed>> $formType
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly FormFactoryInterface $forms,
        private readonly SluggerInterface $slugger,
        private readonly AccountSpaces $spaces,
        private readonly string $userClass,
        private readonly string $access,
        private readonly string $avatarDirectory,
        private readonly string $avatarPublicPath,
        private readonly string $formType = ProfileFormType::class,
        private readonly ?string $showAttribute = null,
    ) {
    }

    public function edit(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->access);
        $account = $this->getUser();
        if (!$account instanceof $this->userClass) {
            throw $this->createAccessDeniedException(\sprintf('The profile edits a %s.', $this->userClass));
        }

        $form = $this->forms->createNamed(self::FORM, $this->formType, $account);
        $form->handleRequest($request);
        $profile = $this->spaces->route($request, 'profile');

        if ($form->isSubmitted() && $form->isValid()) {
            $uploaded = $form->has('avatarFile') ? $form->get('avatarFile')->getData() : null;

            if ($uploaded instanceof UploadedFile && method_exists($account, 'setAvatarPath')) {
                $name = $this->slugger->slug(pathinfo($uploaded->getClientOriginalName(), \PATHINFO_FILENAME))
                    ->lower()->truncate(40)->toString();
                $filename = \sprintf('%s-%s.%s', '' !== $name ? $name : 'avatar', bin2hex(random_bytes(8)), $uploaded->guessExtension() ?? 'bin');

                try {
                    $uploaded->move($this->avatarDirectory, $filename);
                } catch (FileException) {
                    $this->addFlash('error', 'user.profile.flash.upload_failed');

                    return $this->redirectToRoute($profile);
                }

                $account->setAvatarPath(rtrim($this->avatarPublicPath, '/').'/'.$filename);
            }

            $manager = $this->registry->getManagerForClass($this->userClass)
                ?? throw new \LogicException(\sprintf('No entity manager maps "%s" (admin.account.user_class).', $this->userClass));
            $manager->flush();
            $this->addFlash('success', 'user.profile.flash.saved');

            $show = $this->spaces->optionalRoute($request, 'profile_show');

            return null !== $show && null !== $this->showAttribute && $this->isGranted($this->showAttribute) && method_exists($account, 'getId')
                ? $this->redirectToRoute($show, ['id' => $account->getId()])
                : $this->redirectToRoute($profile);
        }

        // ⚠️ A refused form already wrote its values into the signed-in account — the very object the shell shows
        // around it: the header said the refused name, as if saved. The account is read again from the database; the
        // form keeps what was typed, and its errors.
        if ($form->isSubmitted()) {
            $manager = $this->registry->getManagerForClass($this->userClass);

            if ($manager instanceof ObjectManager && $manager->contains($account)) {
                $manager->refresh($account);
            }
        }

        return $this->render($this->spaces->template($request, 'profile'), ['form' => $form] + $this->spaces->context($request), new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        ));
    }
}
