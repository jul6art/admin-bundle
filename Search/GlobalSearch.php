<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Search;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The header search engine: the families a product's source hands it, searched the same way for
 * everyone.
 *
 * It moved here from three products that carried it as near-twins (cereezer, superp, cegeta). What
 * it guarantees, so no product re-decides it:
 *
 * - **two characters, on the server** — the Stimulus controller has its own threshold, the route
 *   can be called by hand;
 * - **the comparison of `api-bundle`'s `OrSearchFilter`** on text columns — `LOWER(field) LIKE
 *   LOWER('%term%')`, so a panel and the list its "see all" opens count alike. Unlike the filter it
 *   casts no number: declare text fields only (see `SearchFamily::$fields`). `%` and `_` are not escaped, for the same
 *   reason: escaping here only would make the two counts diverge;
 * - **five rows, ordered case-insensitively** like `CaseInsensitiveOrderFilter` orders the list;
 * - **a `COUNT` only when a family is saturated** — three rows out of five asked, the total IS three;
 * - **label and URL only** in each result: selected scalars, never an entity, so a field added to
 *   an entity cannot widen what a keystroke returns;
 * - **nothing kept** — no table, no log line.
 *
 * Without a source it searches nothing: it never guesses a family.
 */
final readonly class GlobalSearch
{
    public const int PER_GROUP = 5;

    public const int MIN_LENGTH = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urls,
        private ?GlobalSearchSourceInterface $source = null,
    ) {
    }

    /**
     * @return array<string, SearchGroup> family key → group, empty families left out
     */
    public function search(string $term): array
    {
        $term = \trim($term);

        if (\mb_strlen($term) < self::MIN_LENGTH || !$this->source instanceof GlobalSearchSourceInterface) {
            return [];
        }

        $groups = [];

        foreach ($this->source->families() as $family) {
            $group = $this->query($this->source, $family, $term);

            // An empty family is left out: "Sites — 0" is noise where one looks for signal.
            if ([] !== $group->results) {
                $groups[$family->key] = $group;
            }
        }

        return $groups;
    }

    /**
     * The JSON the `search--global` controller reads: `{ key: { total, url, results: [{label, url}] } }`,
     * and `[]` when nothing matched.
     */
    public function respond(string $term): JsonResponse
    {
        $payload = [];

        foreach ($this->search($term) as $key => $group) {
            $payload[$key] = [
                'total' => $group->total,
                'url' => $group->url,
                'results' => \array_map(
                    static fn (SearchResult $result): array => ['label' => $result->label, 'url' => $result->url],
                    $group->results,
                ),
            ];
        }

        return new JsonResponse($payload);
    }

    private function query(GlobalSearchSourceInterface $source, SearchFamily $family, string $term): SearchGroup
    {
        $builder = $this->entityManager->getRepository($family->entityClass)->createQueryBuilder('e');

        $clauses = \array_map(static fn (string $field): string => \sprintf('LOWER(e.%s) LIKE LOWER(:term)', $field), $family->fields);

        // ⚠️ Parenthesised: the source's `andWhere()` must bind to the whole OR, not to its last term.
        $builder->andWhere('('.\implode(' OR ', $clauses).')')->setParameter('term', '%'.$term.'%');

        $source->scope($family, $builder);

        /** @var list<array{id: int|string, label: string|null}> $rows */
        $rows = (clone $builder)
            ->select(\sprintf('e.id AS id, %1$s AS label, LOWER(%1$s) AS HIDDEN sortLabel', $family->label))
            ->orderBy('sortLabel', 'ASC')
            ->setMaxResults(self::PER_GROUP)
            ->getQuery()
            ->getArrayResult();

        $results = [];

        foreach ($rows as $row) {
            $results[] = new SearchResult((string) ($row['label'] ?? ''), $this->urls->generate($family->showRoute, ['id' => $row['id']]));
        }

        $total = \count($results) < self::PER_GROUP
            ? \count($results)
            : (int) (clone $builder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        return new SearchGroup(
            $results,
            $total,
            null === $family->listRoute ? null : $this->urls->generate($family->listRoute, ['search' => $term]),
        );
    }
}
