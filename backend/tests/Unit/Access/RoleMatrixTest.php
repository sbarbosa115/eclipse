<?php

namespace App\Tests\Unit\Access;

use App\Access\UI\Http\Security\RoleMatrixVoter;
use App\Access\UI\Http\Security\SecurityUser;
use App\Shared\UI\Http\Security\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The role matrix of §8 as changed on 2026-10-04 (the accountant may do every document and books action; users and
 * the company settings stay the owner's), as the voter grants it. This table is the documentation: a change of who may do
 * what is a change here first.
 *
 * | Permission      | owner | billing | accountant | What it covers                                                        |
 * |-----------------|-------|---------|------------|-----------------------------------------------------------------------|
 * | MANAGE_USERS    | yes   | no      | no         | invite, change roles, deactivate (Configuración › Usuarios)          |
 * | MANAGE_SETTINGS | yes   | no      | no         | company profile, invoicing resolution, numbering                      |
 * | MANAGE_BOOKS    | yes   | no      | yes        | chart of accounts, posting rules, taxes, payment methods, lock date   |
 * | VIEW_BOOKS      | yes   | no      | yes        | libro diario, balance de prueba, statements                           |
 * | WRITE_DOCUMENTS | yes   | yes     | yes        | terceros, products, quotations, invoices, receipts, payments, voids   |
 * | READ_DOCUMENTS  | yes   | yes     | yes        | every list, document and PDF                                          |
 */
final class RoleMatrixTest extends TestCase
{
    private const MATRIX = [
        Permission::MANAGE_USERS => ['owner'],
        Permission::MANAGE_SETTINGS => ['owner'],
        Permission::MANAGE_BOOKS => ['owner', 'accountant'],
        Permission::VIEW_BOOKS => ['owner', 'accountant'],
        Permission::WRITE_DOCUMENTS => ['owner', 'billing', 'accountant'],
        Permission::READ_DOCUMENTS => ['owner', 'billing', 'accountant'],
    ];

    /** @return iterable<string, array{string, string, bool}> */
    public static function cells(): iterable
    {
        foreach (self::MATRIX as $permission => $roles) {
            foreach (['owner', 'billing', 'accountant'] as $role) {
                yield "$role $permission" => [$role, $permission, \in_array($role, $roles, true)];
            }
        }
    }

    #[DataProvider('cells')]
    public function testEachRoleMayDoWhatSection8Says(string $role, string $permission, bool $granted): void
    {
        $vote = (new RoleMatrixVoter())->vote(self::tokenOf($role), null, [$permission]);

        self::assertSame($granted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $vote, \sprintf('%s %s %s.', $role, $granted ? 'may' : 'may not', $permission));
    }

    public function testTheMatrixNamesEveryPermission(): void
    {
        $declared = array_values(array_filter((new \ReflectionClass(Permission::class))->getConstants(), 'is_string'));

        self::assertSame($declared, array_keys(self::MATRIX), 'A new permission gets a row in this table (and in Permission::ALL, which the voter test walks).');
    }

    public function testNobodySignedInMayDoAnything(): void
    {
        foreach (Permission::ALL as $permission) {
            self::assertSame(VoterInterface::ACCESS_DENIED, (new RoleMatrixVoter())->vote(new NullToken(), null, [$permission]));
        }
    }

    public function testTheVoterAbstainsOnWhatIsNotAPermission(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new RoleMatrixVoter())->vote(self::tokenOf('owner'), null, ['ROLE_USER']));
    }

    private static function tokenOf(string $role): UsernamePasswordToken
    {
        $user = new SecurityUser(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), "$role@acme.co", $role, true, 'hash');

        return new UsernamePasswordToken($user, 'api', $user->getRoles());
    }
}
