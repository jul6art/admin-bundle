<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Jul6Art\AdminBundle\Controller\Security\AccountProfileController;
use Jul6Art\AdminBundle\Form\Security\ProfileFormType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DomCrawler\Field\FileFormField;

/**
 * The profile of the signed-in account (ADR-0001, T20 lot 3): what guards it, its names, its picture, where it
 * returns after saving.
 */
#[CoversClass(AccountProfileController::class)]
#[CoversClass(ProfileFormType::class)]
final class ProfileScreenTest extends AccountScreensTestCase
{
    public function testAVisitorSignedOutIsSentToTheSignIn(): void
    {
        $this->start();

        $this->client->request('GET', '/admin/account/profile');

        self::assertResponseRedirects('/login');
    }

    public function testTheAccessIsTheConfigurations(): void
    {
        $this->start(['profile' => ['access' => 'ROLE_PROFILE_UPDATE']]);
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $this->client->request('GET', '/admin/account/profile');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('ROLE_PROFILE_UPDATE', self::getContainer()->getParameter('admin.account.profile.access'));
    }

    public function testTheNamesAreSavedAndTheProfileComesBack(): void
    {
        $this->start();
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        self::assertSame('admin', $crawler->filter('main')->attr('data-space'));
        self::assertSame('csrf-token', $crawler->filter('[name="profile_form[_token]"]')->attr('value'), 'The application\'s stateless token id, as its own forms.');
        $this->client->submit($crawler->filter('form')->form(['profile_form[firstName]' => 'Augusta', 'profile_form[lastName]' => 'King']));

        self::assertResponseRedirects('/admin/account/profile');
        self::assertSame(['user.profile.flash.saved'], $this->flashes('success'));
        self::assertSame('Augusta', $this->find('ada@example.test')?->getFirstName());
    }

    public function testAnAccountThatMayReadItsPageReturnsThere(): void
    {
        $this->start(['profile' => ['show_attribute' => 'ROLE_USER'], 'spaces' => ['admin' => ['routes' => ['profile_show' => 'admin_user_show']]]]);
        $member = $this->member('ada@example.test');
        $this->client->loginUser($member, 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $this->client->submit($crawler->filter('form')->form());

        self::assertResponseRedirects('/admin/users/'.$member->getId());
    }

    public function testAnAccountThatMayNotReadItsPageStaysOnTheProfile(): void
    {
        $this->start(['profile' => ['show_attribute' => 'ROLE_USER_READ'], 'spaces' => ['admin' => ['routes' => ['profile_show' => 'admin_user_show']]]]);
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $this->client->submit($crawler->filter('form')->form());

        self::assertResponseRedirects('/admin/account/profile', null, 'Not a 403 after a successful save.');
    }

    /**
     * cegeta's customer area: the account may read users in its tenant, but the page of an account lives in the
     * platform's back-office, closed to it. The space declares no page: back to its own profile, never a 403.
     */
    public function testASpaceWithoutAnAccountPageStaysOnItsProfile(): void
    {
        $this->start([
            'profile' => ['show_attribute' => 'ROLE_USER'],
            'spaces' => [
                'admin' => ['routes' => ['profile_show' => 'admin_user_show']],
                'customer' => ['routes' => ['profile' => 'app_account_profile_edit', 'profile_show' => '']],
            ],
        ]);
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/app/account/profile');
        self::assertSame('customer', $crawler->filter('main')->attr('data-space'));
        $this->client->submit($crawler->filter('form')->form());

        self::assertResponseRedirects('/app/account/profile');
    }

    public function testThePictureIsRenamedAndStored(): void
    {
        $directory = sys_get_temp_dir().'/jul6art-admin-bundle-avatars-'.bin2hex(random_bytes(4));
        $this->start(['profile' => ['avatar_directory' => $directory]]);
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $form = $crawler->filter('form')->form();
        $this->upload($form['profile_form[avatarFile]'], $this->png('../../My Photo.PNG'));
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/account/profile');
        $path = $this->find('ada@example.test')?->getAvatarPath();
        self::assertIsString($path);
        self::assertMatchesRegularExpression('#^uploads/avatars/[0-9a-f]{12}-my-photo-[0-9a-f]{16}\.png$#', $path, 'The name is rebuilt, the extension read from the content.');
        self::assertFileExists($directory.'/'.basename($path));

        array_map(unlink(...), glob($directory.'/*') ?: []);
        rmdir($directory);
    }

    public function testAPictureThatCannotBeStoredSaysSo(): void
    {
        // A FILE where the directory should be: the move fails.
        $blocked = tempnam(sys_get_temp_dir(), 'avatars');
        self::assertIsString($blocked);
        $this->start(['profile' => ['avatar_directory' => $blocked]]);
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $form = $crawler->filter('form')->form();
        $this->upload($form['profile_form[avatarFile]'], $this->png('face.png'));
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/account/profile');
        self::assertSame(['user.profile.flash.upload_failed'], $this->flashes('error'));
        self::assertNull($this->find('ada@example.test')?->getAvatarPath());
        unlink($blocked);
    }

    public function testAnEmptiedNameIsAFormError(): void
    {
        $this->start();
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $this->client->submit($crawler->filter('form')->form(['profile_form[firstName]' => str_repeat('a', 101)]));

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * ⚠️ A refused profile does not change the signed-in account the page shows around the form: the form wrote its
     * values into that very object, and the shell's header showed the refused name — nothing was stored, but the page
     * said otherwise (seen in Chrome on a generated back office, 2026-11-14). The form keeps what was typed, with
     * its error; the account is read again from the database.
     */
    public function testARefusedProfileLeavesTheSignedInAccountAsStored(): void
    {
        $this->start();
        $this->client->loginUser($this->member('ada@example.test'), 'main');

        $crawler = $this->client->request('GET', '/admin/account/profile');
        $crawler = $this->client->submit($crawler->filter('form')->form(['profile_form[firstName]' => str_repeat('a', 101)]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Ada', $crawler->filter('header[data-account]')->text(), 'The account around the form is the stored one.');
        self::assertSame(str_repeat('a', 101), $crawler->filter('input[name="profile_form[firstName]"]')->attr('value'), 'The form keeps what was typed.');
    }

    private function upload(mixed $field, string $file): void
    {
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);
    }

    private function png(string $name): string
    {
        $file = sys_get_temp_dir().'/'.bin2hex(random_bytes(6)).'-'.basename($name);
        // The smallest valid PNG: one transparent pixel.
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true));

        return $file;
    }
}
