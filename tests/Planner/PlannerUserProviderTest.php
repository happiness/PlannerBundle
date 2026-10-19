<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\UserRepository;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class PlannerUserProviderTest extends TestCase
{
    /**
     * @param array<string> $granted
     */
    private function createSecurity(array $granted): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => \in_array($attribute, $granted, true));

        return $security;
    }

    public function testUserWithoutPermissionOnlySeesSelf(): void
    {
        $user = new User();
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('getUsersForQuery');

        $provider = new PlannerUserProvider($repository, $this->createSecurity([]));

        $this->assertSame([$user], $provider->getVisibleUsers($user));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privilegedPermissions(): array
    {
        return [
            'view_other_planner' => ['view_other_planner'],
            'view_all_data' => ['view_all_data'],
        ];
    }

    /**
     * @dataProvider privilegedPermissions
     */
    public function testPrivilegedUserSeesUsersFromQuery(string $permission): void
    {
        $user = new User();
        $other = new User();

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('getUsersForQuery')
            ->with($this->callback(static fn (UserQuery $query): bool => $query->getCurrentUser() === $user && $query->getOrderBy() === 'username'))
            ->willReturn([$user, $other]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity([$permission]));

        $this->assertSame([$user, $other], $provider->getVisibleUsers($user));
    }
}
