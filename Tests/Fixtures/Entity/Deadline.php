<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Entity\Traits\DeadlineTrait;

/**
 * What a consuming application writes for `admin.deadlines`: its own entity, its own identifier,
 * the trait for the rest.
 */
#[ORM\Entity]
#[ORM\Table(name: 'deadline')]
class Deadline implements DeadlineInterface
{
    use DeadlineTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
