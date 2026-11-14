<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\UserImport;

use Jul6Art\AdminBundle\Tests\Account\AccountScreensTestCase;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;

/**
 * The accounts' import of `admin.user_import`, through dataflow-bundle's screens (1.44).
 */
final class UserImportScreenTest extends AccountScreensTestCase
{
    // The container's reader, on its default dialect here: commas. An application rebinds it once, and the import
    // follows — the model it hands out is read back the same way.

    public function testOnlyAnAccountThatMayCreateAccountsReachesTheScreens(): void
    {
        $this->start();
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        foreach ([['GET', '/admin/users/import/new'], ['GET', '/admin/users/import/mapping'], ['POST', '/admin/users/import/execute']] as [$method, $uri]) {
            $this->client->request($method, $uri);
            self::assertResponseStatusCodeSame(403, $method.' '.$uri);
        }
    }

    public function testAccountsAreCreatedActiveWithARandomPasswordAndTheirRole(): void
    {
        $this->signInAs(['ROLE_ADMIN']);

        $this->import("firstName,lastName,email,role\nGrace,Hopper,GRACE@Example.test,ROLE_ADMIN\nAlan,Turing,alan@example.test,\n");

        self::assertResponseIsSuccessful();
        $grace = $this->find('grace@example.test');
        self::assertInstanceOf(Member::class, $grace, 'The address is stored the way the entity normalises it.');
        self::assertSame('Grace', $grace->getFirstName());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $grace->getRoles());
        self::assertTrue($grace->isActive());
        self::assertSame(64, \strlen($grace->getPassword()), 'A random password, opened by "forgotten password".');
        self::assertSame(['ROLE_USER'], $this->find('alan@example.test')?->getRoles(), 'No role: the implicit one only.');
    }

    public function testTheSameAddressTwiceInAFileOrAlreadyStoredCreatesNoSecondAccount(): void
    {
        $this->signInAs(['ROLE_ADMIN']);
        $this->member('known@example.test');

        $crawler = $this->import("firstName,lastName,email\nGrace,Hopper,grace@example.test\nGrace,Hopper,Grace@example.test\nAda,Lovelace,KNOWN@example.test\n");

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->entityManager()->getRepository(Member::class)->count(['email' => 'grace@example.test']));
        self::assertSame('Lovelace', $this->find('known@example.test')?->getLastName(), 'The stored account is left as it was.');
        self::assertStringContainsString('1', $crawler->filter('.panel')->first()->text(), 'One account imported.');
    }

    public function testARoleOutsideTheConfiguredListIsRefused(): void
    {
        $this->signInAs(['ROLE_ADMIN']);

        $crawler = $this->import("firstName,lastName,email,role\nMallory,Root,mallory@example.test,ROLE_GOD\n");

        self::assertNull($this->find('mallory@example.test'));
        self::assertStringContainsString('user.import.error.unknown_role', $crawler->filter('body')->text());
    }

    /**
     * ⚠️ An administrator holds the right to create accounts: importing a super administrator at an address of their
     * own, then opening it by "forgotten password", was an elevation four copies of this import still allowed.
     */
    public function testOnlyASuperAdministratorImportsASuperAdministrator(): void
    {
        $this->signInAs(['ROLE_ADMIN']);
        $crawler = $this->import("firstName,lastName,email,role\nMallory,Root,mallory@example.test,ROLE_SUPER_ADMIN\n");

        self::assertNull($this->find('mallory@example.test'));
        self::assertStringContainsString('user.import.error.role_forbidden', $crawler->filter('body')->text());

        $this->signInAs(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], 'root@example.test');
        $this->import("firstName,lastName,email,role\nMargaret,Hamilton,margaret@example.test,ROLE_SUPER_ADMIN\n");

        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $this->find('margaret@example.test')?->getRoles());
    }

    public function testTheNameAndTheAddressMustHaveAColumn(): void
    {
        $this->signInAs(['ROLE_ADMIN']);
        $this->upload("firstName,email\nGrace,grace@example.test\n");
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->submit($crawler->filter('form')->form());

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('lastName', $crawler->filter('[data-dataflow-error]')->text());
        self::assertNull($this->find('grace@example.test'));
    }

    /**
     * @param list<string> $roles
     */
    private function signInAs(array $roles, string $email = 'admin@example.test'): void
    {
        if (!isset($this->client)) {
            $this->start();
        }

        $admin = $this->entityManager()->getRepository(Member::class)->findOneBy(['email' => $email]) ?? $this->member($email);
        $admin->setRoles($roles);
        $this->entityManager()->flush();
        $this->client->loginUser($admin, 'main');
    }

    private function import(string $csv): Crawler
    {
        $this->upload($csv);
        $crawler = $this->client->followRedirect();

        return $this->client->submit($crawler->filter('form')->form());
    }

    private function upload(string $csv): void
    {
        $form = $this->client->request('GET', '/admin/users/import/new')->filter('form')->form();
        $path = sys_get_temp_dir().'/admin-user-import-'.bin2hex(random_bytes(4)).'.csv';
        file_put_contents($path, $csv);
        $field = $form['file'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($path);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/users/import/mapping', 303);
    }
}
