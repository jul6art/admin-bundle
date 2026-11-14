<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\UserImport;

use Jul6Art\DataflowBundle\Import\DuplicateResolverInterface;
use Jul6Art\DataflowBundle\Import\ImportRunner;
use Jul6Art\DataflowBundle\Import\Spec\ImportSpec;
use Jul6Art\DataflowBundle\Import\Web\AbstractImportDefinition;
use Jul6Art\DataflowBundle\Import\Web\ImportAccess;
use Jul6Art\DataflowBundle\Import\Web\ImportContext;
use Jul6Art\DataflowBundle\Import\Web\ImportLink;
use Jul6Art\DataflowBundle\Import\Web\ImportResult;
use Jul6Art\DataflowBundle\Import\Web\ImportRoutes;
use Jul6Art\DataflowBundle\Io\Reader\TabularReaderInterface;
use Jul6Art\DataflowBundle\Io\TabularWriterInterface;

/**
 * The accounts' import of `admin.user_import`: the screens of dataflow-bundle, the fields of an account, its roles
 * (ADR-0001 of dataflow-bundle).
 *
 * ⚠️ **The right is the one to CREATE an account** (`user:create` by default), not a code of its own: importing
 * fifty accounts is creating one, fifty times. A separate code would give someone the import without the creation.
 */
final class UserImportDefinition extends AbstractImportDefinition
{
    public function __construct(
        private readonly ImportRunner $runner,
        private readonly UserRowMapper $mapper,
        private readonly UserDuplicateResolver $duplicates,
        private readonly TabularReaderInterface $reader,
        private readonly TabularWriterInterface $writer,
        private readonly string $permission,
        private readonly ImportRoutes $routes,
        private readonly string $returnRoute,
        private readonly string $translationDomain,
        private readonly string $translationPrefix,
        private readonly ?int $maxBytes = null,
    ) {
    }

    #[\Override]
    public function fields(): array
    {
        return UserRowMapper::FIELDS;
    }

    /** The three every line needs: refused at the mapping rather than on every line of the report. */
    #[\Override]
    public function requiredFields(): array
    {
        return ['firstName', 'lastName', 'email'];
    }

    #[\Override]
    public function reader(): TabularReaderInterface
    {
        return $this->reader;
    }

    #[\Override]
    public function writer(): TabularWriterInterface
    {
        return $this->writer;
    }

    #[\Override]
    public function import(string $filePath, array $mapping, ImportContext $context): ImportResult
    {
        return new ImportResult($this->runner->run(new ImportSpec($filePath, $mapping), $this->mapper, $this->reader, $this->duplicates));
    }

    #[\Override]
    public function access(ImportContext $context): array
    {
        return [new ImportAccess($this->permission)];
    }

    #[\Override]
    public function translationDomain(): string
    {
        return $this->translationDomain;
    }

    #[\Override]
    public function translationPrefix(): string
    {
        return $this->translationPrefix;
    }

    #[\Override]
    public function routes(): ImportRoutes
    {
        return $this->routes;
    }

    #[\Override]
    public function returnTo(ImportContext $context): ImportLink
    {
        return new ImportLink($this->returnRoute);
    }

    #[\Override]
    public function duplicateResolver(ImportContext $context): ?DuplicateResolverInterface
    {
        return $this->duplicates;
    }

    #[\Override]
    public function maxBytes(): ?int
    {
        return $this->maxBytes;
    }
}
