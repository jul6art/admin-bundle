<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Deadline\DeadlineNotifier;
use Jul6Art\AdminBundle\Deadline\ReminderStage;
use Jul6Art\AdminBundle\Form\DeadlineType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The deadlines' screen (`admin.deadlines`): the list, the nearest first, with the days left; a
 * deadline typed, corrected, renewed, deleted. Those the application computes
 * ({@see \Jul6Art\AdminBundle\Deadline\DeadlineProviderInterface}) are listed, never edited — the
 * next run would write them back.
 *
 * Every action asks `admin.deadlines.access` first (`ROLE_SUPER_ADMIN` by default): the menu entry
 * the application adds must carry the same attribute — the container parameter of the same name says
 * which, for a test that walks the menu. Its flashes are KEYS, like the appearance
 * screen's: the toasts translate them in `messages`, where the application declares them.
 *
 * ## Wiring
 *
 * ```yaml
 * # config/routes/admin.yaml
 * admin_deadlines:
 *     resource: '@AdminBundle/Controller/DeadlineController.php'
 *     type: attribute
 *     prefix: /admin
 * ```
 *
 * ⚠️ Keep the route names: `admin_deadline_index` is the link of every reminder.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DeadlineController extends AbstractController
{
    public const string ROUTE = 'admin_deadline_index';

    /** The longest rhythm a deadline can have: ten years. */
    public const int MAX_PERIOD_DAYS = 3650;

    /**
     * @param class-string<DeadlineInterface> $entityClass
     */
    public function __construct(
        private readonly DeadlineNotifier $deadlines,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $forms,
        private readonly TranslatorInterface $translator,
        private readonly string $entityClass,
        private readonly string $access,
    ) {
    }

    #[Route('/deadlines', name: self::ROUTE, methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted($this->access);

        $today = $this->deadlines->today();
        $rows = [];
        $stats = ['past' => 0, 'soon' => 0, 'month' => 0, 'total' => 0];
        $reminders = [];
        foreach ($this->deadlines->synchronise() as $deadline) {
            $days = $this->deadlines->daysLeft($deadline);
            $status = self::status($days);
            $period = $deadline->getPeriodDays();
            ++$stats['total'];
            if (isset($stats[$status])) {
                ++$stats[$status];
            }

            $next = null === $days ? null : self::nextReminder($deadline, $days, $today);
            if (null !== $next) {
                $reminders[] = $next + ['deadline' => $deadline];
            }

            $rows[] = [
                'deadline' => $deadline,
                'days' => $days,
                'status' => $status,
                // La part de la période déjà écoulée : une barre, seulement quand il y a un rythme.
                'progress' => null !== $period && $period > 0 && null !== $days ? (int) round(100 * min($period, max(0, $period - $days)) / $period) : null,
                'next' => $next,
            ];
        }
        usort($reminders, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $this->render('@Admin/deadline/index.html.twig', [
            'rows' => $rows,
            'stats' => $stats,
            'reminders' => \array_slice($reminders, 0, 6),
            'today' => $today,
        ]);
    }

    /**
     * Where a deadline stands, by its days left: `past` (the day itself included — it expires
     * today), `soon` (a week), `month` (thirty days), `later`, or `undated`.
     */
    private static function status(?int $days): string
    {
        return match (true) {
            null === $days => 'undated',
            $days <= 0 => 'past',
            $days <= 7 => 'soon',
            $days <= 30 => 'month',
            default => 'later',
        };
    }

    /**
     * The next reminder of a deadline: the stage reached and not sent yet goes at the next run
     * (today), otherwise the next stage to come, on its date. Null once the day itself was sent.
     *
     * @return array{date: \DateTimeImmutable, stage: ReminderStage}|null
     */
    private static function nextReminder(DeadlineInterface $deadline, int $days, \DateTimeImmutable $today): ?array
    {
        $notified = $deadline->getNotifiedStage();
        $reached = ReminderStage::reachedAt($days);
        if ($reached instanceof ReminderStage && (null === $notified || $notified > $reached->value)) {
            return ['date' => $today, 'stage' => $reached];
        }

        foreach (ReminderStage::cases() as $stage) {
            if ($stage->value < $days) {
                return ['date' => $today->modify(\sprintf('+%d days', $days - $stage->value)), 'stage' => $stage];
            }
        }

        return null;
    }

    #[Route('/deadlines/new', name: 'admin_deadline_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->access);

        return $this->form($request, new ($this->entityClass)());
    }

    #[Route('/deadlines/{id}/edit', name: 'admin_deadline_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->denyAccessUnlessGranted($this->access);

        return $this->form($request, $this->typed($id));
    }

    /** « Renewed »: today plus the period — the three reminders re-armed. */
    #[Route('/deadlines/{id}/renew', name: 'admin_deadline_renew', methods: ['POST'])]
    public function renew(Request $request, string $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->access);
        $deadline = $this->typed($id);

        if ($this->isCsrfTokenValid('admin_deadline_'.$id, (string) $request->request->get('_token')) && null !== $deadline->getPeriodDays()) {
            $deadline->renew($this->deadlines->today());
            $this->entityManager->flush();
            $this->addFlash('success', 'deadline.flash.renewed');
        }

        return $this->redirectToRoute(self::ROUTE);
    }

    #[Route('/deadlines/{id}/delete', name: 'admin_deadline_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->access);
        $deadline = $this->typed($id);

        if ($this->isCsrfTokenValid('admin_deadline_'.$id, (string) $request->request->get('_token'))) {
            $this->entityManager->remove($deadline);
            $this->entityManager->flush();
            $this->addFlash('success', 'deadline.flash.deleted');
        }

        return $this->redirectToRoute(self::ROUTE);
    }

    private function form(Request $request, DeadlineInterface $deadline): Response
    {
        $form = $this->forms->create(DeadlineType::class, $deadline, ['data_class' => $this->entityClass]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->checked($form, $deadline)) {
            $deadline->setLabel(trim($deadline->getLabel()));
            $this->entityManager->persist($deadline);
            $this->entityManager->flush();
            $this->addFlash('success', 'deadline.flash.saved');

            return $this->redirectToRoute(self::ROUTE);
        }

        return $this->render('@Admin/deadline/edit.html.twig', [
            'form' => $form,
            'deadline' => $deadline,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * The rules of a deadline, on their fields: the bundle does not require the validator.
     *
     * @param FormInterface<DeadlineInterface> $form
     */
    private function checked(FormInterface $form, DeadlineInterface $deadline): bool
    {
        $errors = [];
        $label = trim($deadline->getLabel());
        if ('' === $label || mb_strlen($label) > 120) {
            $errors['label'] = 'deadline.error.label';
        }
        if (null === $form->get('dueOn')->getData()) {
            $errors['dueOn'] = 'deadline.error.due_on';
        }
        $period = $deadline->getPeriodDays();
        if (null !== $period && ($period < 1 || $period > self::MAX_PERIOD_DAYS)) {
            $errors['periodDays'] = 'deadline.error.period_days';
        }
        $url = $deadline->getRenewUrl();
        if (null !== $url && (mb_strlen($url) > 500 || 1 !== preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $url))) {
            $errors['renewUrl'] = 'deadline.error.renew_url';
        }

        foreach ($errors as $field => $key) {
            $form->get($field)->addError(new FormError($this->translator->trans($key, [], DeadlineNotifier::DOMAIN)));
        }

        return [] === $errors;
    }

    /** A deadline typed in the screen; one the application computes is not editable here. */
    private function typed(string $id): DeadlineInterface
    {
        $deadline = $this->entityManager->find($this->entityClass, $id);
        if (!$deadline instanceof DeadlineInterface || null !== $deadline->getSource()) {
            throw new NotFoundHttpException();
        }

        return $deadline;
    }
}
