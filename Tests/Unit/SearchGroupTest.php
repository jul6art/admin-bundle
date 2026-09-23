<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\Search\SearchGroup;
use Jul6Art\AdminBundle\Search\SearchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchGroup::class)]
final class SearchGroupTest extends TestCase
{
    /**
     * The "see all" URL is optional: a family without a list of its own — or a product that does not
     * link — serialises exactly as before 1.16.
     */
    public function testTheSeeAllUrlIsOptional(): void
    {
        $group = new SearchGroup([new SearchResult('Ada', '/a/1')], 1);

        self::assertNull($group->url);
    }

    public function testASeeAllUrlIsCarriedToThePayload(): void
    {
        $group = new SearchGroup([], 12, '/items?search=bos');

        self::assertSame(['results' => [], 'total' => 12, 'url' => '/items?search=bos'], get_object_vars($group));
    }
}
