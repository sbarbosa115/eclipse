<?php

namespace App\Tests\Unit\Access;

use App\Access\UI\Http\Security\SecurityUser;
use App\Access\UI\Http\Security\SignInFailed;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Security audit 2026-10-04, finding 5: a wrong password took ~400 ms (the hash is checked) and an unknown e-mail
 * ~50 ms (nothing to check), so the answer's time told which e-mails have an account. An unknown e-mail now spends
 * one hash too.
 */
final class SignInFailedTest extends TestCase
{
    public function testAnUnknownEmailSpendsAHashLikeAWrongPassword(): void
    {
        $hasher = new class implements PasswordHasherInterface {
            public int $hashes = 0;

            public function hash(#[\SensitiveParameter] string $plainPassword): string
            {
                ++$this->hashes;

                return 'hashed';
            }

            public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
            {
                return false;
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };
        $factory = new class($hasher) implements PasswordHasherFactoryInterface {
            public ?string $askedFor = null;

            public function __construct(private readonly PasswordHasherInterface $hasher)
            {
            }

            public function getPasswordHasher(mixed $user): PasswordHasherInterface
            {
                $this->askedFor = \is_string($user) ? $user : $user::class;

                return $this->hasher;
            }
        };
        $failed = new SignInFailed($factory);
        $request = Request::create('/api/v1/auth/sign-in', 'POST', content: '{"email":"nadie@x.co","password":"adivina-123"}');

        $unknown = $failed->onAuthenticationFailure($request, new BadCredentialsException('Bad credentials.', 0, new UserNotFoundException()));
        self::assertSame(401, $unknown->getStatusCode());
        self::assertSame(1, $hasher->hashes, 'An unknown e-mail spends one hash.');
        self::assertSame(SecurityUser::class, $factory->askedFor, 'With the hasher a real user has.');

        $failed->onAuthenticationFailure($request, new BadCredentialsException('The presented password is invalid.'));
        self::assertSame(1, $hasher->hashes, 'A wrong password was already checked against the hash: nothing more.');
    }
}
