<?php

namespace App\Tests\Functional\Access;

use App\Shared\Domain\Model\AuditLog;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;

final class UserAdminApiTest extends ApiTestCase
{
    use InvitesUsers;
    use SignsUp;

    /** Luis accepts an invitation, the owner signs back in and gives him $role. */
    private function colleague(string $role = 'billing', string $email = 'luis@acme.co'): string
    {
        $user = $this->invite($email, 'owner' === $role ? 'billing' : $role);
        $this->acceptAs($this->linkSentTo($email, 'invitacion'));
        $this->signOut();
        $this->signIn('ana@acme.co');
        if ('owner' === $role) {
            $this->sendJson('PUT', "/api/v1/users/{$user['id']}/role", ['role' => 'owner']);
            self::assertResponseIsSuccessful();
        }

        return $user['id'];
    }

    /** @return array<mixed> */
    private function users(): array
    {
        $body = $this->getJson('/api/v1/users');
        self::assertResponseIsSuccessful();

        return $body['items'];
    }

    public function testTheOwnerSeesEveryUserOfTheCompanyOnly(): void
    {
        $this->signUp('otra@empresa.co', '800197268', 'Otra Empresa');
        $this->signOut();
        $owner = $this->signUp();
        $this->colleague('accountant');
        $this->invite('eva@acme.co', 'billing');

        $users = $this->users();

        self::assertSame(['ana@acme.co', 'eva@acme.co', 'luis@acme.co'], array_column($users, 'email'), "By name, then e-mail; another company's users are not there.");
        $ana = $users[0];
        self::assertSame($owner['user_id'], $ana['id']);
        self::assertSame('Ana Pérez', $ana['name']);
        self::assertSame('owner', $ana['role']);
        self::assertSame('active', $ana['status']);
        self::assertTrue($ana['is_you'], 'The list marks the person looking at it.');
        self::assertNotNull($ana['last_sign_in_at']);
        self::assertSame('invited', $users[1]['status']);
        self::assertSame('', $users[1]['name'], 'An invitee has no name until they accept.');
        self::assertNotNull($users[1]['invitation_expires_at'], 'An invitee shows until when their link works.');
        self::assertNull($users[2]['invitation_expires_at']);
        self::assertSame('accountant', $users[2]['role']);
    }

    public function testOnlyTheOwnerSeesTheUsers(): void
    {
        $this->signUp();
        $this->signInInvitee('luis@acme.co', 'accountant');

        $this->getJson('/api/v1/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheOwnerChangesARoleAndItIsAudited(): void
    {
        $owner = $this->signUp();
        $luis = $this->colleague('billing');

        $user = $this->sendJson('PUT', "/api/v1/users/$luis/role", ['role' => 'accountant']);

        self::assertResponseIsSuccessful();
        self::assertSame('accountant', $user['role']);
        $log = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findOneBy(['action' => 'user.role_changed']);
        self::assertInstanceOf(AuditLog::class, $log);
        self::assertSame($owner['user_id'], $log->userId()?->toRfc4122());
        self::assertSame($luis, $log->subjectId()?->toRfc4122());
        self::assertSame(['from' => 'billing', 'to' => 'accountant'], $log->data());
    }

    public function testTheLastActiveOwnerCannotBeDemoted(): void
    {
        $owner = $this->signUp();

        $body = $this->sendJson('PUT', "/api/v1/users/{$owner['user_id']}/role", ['role' => 'accountant']);

        self::assertResponseStatusCodeSame(409, 'A company always keeps an owner who can sign in.');
        self::assertSame('last_owner', $body['error']);
    }

    public function testWithASecondOwnerTheFirstMayStepDown(): void
    {
        $owner = $this->signUp();
        $luis = $this->colleague('billing');
        $this->sendJson('PUT', "/api/v1/users/$luis/role", ['role' => 'owner']);
        self::assertResponseIsSuccessful('The owner may make someone else an owner too.');

        $me = $this->sendJson('PUT', "/api/v1/users/{$owner['user_id']}/role", ['role' => 'accountant']);

        self::assertResponseIsSuccessful();
        self::assertSame('accountant', $me['role']);
    }

    public function testARoleIsOneOfTheThree(): void
    {
        $this->signUp();
        $luis = $this->colleague();

        $body = $this->sendJson('PUT', "/api/v1/users/$luis/role", ['role' => 'admin']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('role', $body['violations'][0]['field'] ?? null);
    }

    public function testADeactivatedUserIsSignedOutOnTheirNextRequestAndCannotSignIn(): void
    {
        $this->signUp();
        $luis = $this->invite('luis@acme.co', 'billing');
        $token = $this->linkSentTo('luis@acme.co', 'invitacion');
        $owner = $this->client->getCookieJar()->all();

        // Luis signs in on his own browser…
        $this->client->getCookieJar()->clear();
        $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => 'Luis', 'password' => 'correct horse battery']);
        self::assertResponseIsSuccessful();
        $luisCookies = $this->client->getCookieJar()->all();

        // …while the owner deactivates him on hers.
        $this->client->getCookieJar()->clear();
        foreach ($owner as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $user = $this->sendJson('POST', "/api/v1/users/{$luis['id']}/deactivate", []);
        self::assertResponseIsSuccessful();
        self::assertSame('deactivated', $user['status']);

        $this->client->getCookieJar()->clear();
        foreach ($luisCookies as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->getJson('/api/v1/me');
        self::assertResponseStatusCodeSame(401, "Luis's session ends on his next request.");
        $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => 'luis@acme.co', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(401, 'He cannot sign in again.');
    }

    public function testADeactivatedUserIsReactivated(): void
    {
        $this->signUp();
        $luis = $this->colleague();
        $this->sendJson('POST', "/api/v1/users/$luis/deactivate", []);

        $user = $this->sendJson('POST', "/api/v1/users/$luis/reactivate", []);

        self::assertResponseIsSuccessful();
        self::assertSame('active', $user['status']);
        $actions = array_map(static fn (AuditLog $l) => $l->action(), static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findBy(['subjectType' => 'user'], ['occurredAt' => 'ASC']));
        self::assertSame(['user.invited', 'user.invitation_accepted', 'user.deactivated', 'user.reactivated'], $actions);
        $this->signOut();
        $this->signIn('luis@acme.co');
    }

    public function testTheLastActiveOwnerCannotBeDeactivated(): void
    {
        $owner = $this->signUp();
        $luis = $this->colleague('owner');
        $this->sendJson('POST', "/api/v1/users/$luis/deactivate", []);
        self::assertResponseIsSuccessful('With two owners, one may be deactivated.');

        $body = $this->sendJson('POST', "/api/v1/users/{$owner['user_id']}/deactivate", []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('last_owner', $body['error']);
    }

    public function testNobodyDeactivatesThemselves(): void
    {
        $owner = $this->signUp();
        $this->colleague('owner');

        $body = $this->sendJson('POST', "/api/v1/users/{$owner['user_id']}/deactivate", []);

        self::assertResponseStatusCodeSame(409, 'Deactivating yourself would lock you out mid-session.');
        self::assertSame('cannot_deactivate_yourself', $body['error']);
    }

    public function testAnotherCompanysUserIsNotFound(): void
    {
        $other = $this->signUp('otra@empresa.co', '800197268', 'Otra Empresa');
        $this->signOut();
        $this->signUp();

        foreach ([['PUT', 'role', ['role' => 'billing']], ['POST', 'deactivate', []], ['POST', 'reactivate', []]] as [$method, $path, $payload]) {
            $this->sendJson($method, "/api/v1/users/{$other['user_id']}/$path", $payload);
            self::assertResponseStatusCodeSame(404, "$path: another company's user is not found.");
        }
        $this->sendJson('POST', '/api/v1/users/not-a-uuid/deactivate', []);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyTheOwnerManagesUsers(): void
    {
        $owner = $this->signUp();
        $this->signInInvitee('luis@acme.co', 'accountant');

        $this->sendJson('PUT', "/api/v1/users/{$owner['user_id']}/role", ['role' => 'billing']);
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('POST', "/api/v1/users/{$owner['user_id']}/deactivate", []);
        self::assertResponseStatusCodeSame(403);
    }
}
