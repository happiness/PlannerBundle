<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Voter;

use App\Entity\User;
use App\Security\RolePermissionManager;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, PlannedActivity>
 */
final class PlannedActivityVoter extends Voter
{
    public const VIEW = 'view';
    public const CREATE = 'create';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    private const ALLOWED_ATTRIBUTES = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];

    public function __construct(private readonly RolePermissionManager $permissionManager)
    {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ALLOWED_ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, PlannedActivity::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof PlannedActivity && $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || $user->getId() === null) {
            return false;
        }

        $targetUser = $subject->getUser();
        $isOwner = ($targetUser === null || $targetUser->getId() === $user->getId());
        $isTeamlead = ($targetUser !== null && $user->isTeamleadOfUser($targetUser));
        $hasViewAllData = $this->permissionManager->hasRolePermission($user, 'view_all_data');

        return match ($attribute) {
            self::VIEW => $isOwner
                ? $this->permissionManager->hasRolePermission($user, 'view_planner')
                : ($hasViewAllData || ($isTeamlead && $this->permissionManager->hasRolePermission($user, 'view_other_planner'))),

            self::CREATE => $isOwner
                ? $this->permissionManager->hasRolePermission($user, 'create_planner')
                : ($this->permissionManager->hasRolePermission($user, 'create_planner')
                    && ($hasViewAllData || ($isTeamlead && $this->permissionManager->hasRolePermission($user, 'edit_other_planner')))),

            self::EDIT => $isOwner
                ? $this->permissionManager->hasRolePermission($user, 'edit_planner')
                : ($this->permissionManager->hasRolePermission($user, 'edit_planner')
                    && ($hasViewAllData || ($isTeamlead && $this->permissionManager->hasRolePermission($user, 'edit_other_planner')))),

            self::DELETE => $isOwner
                ? $this->permissionManager->hasRolePermission($user, 'delete_planner')
                : ($this->permissionManager->hasRolePermission($user, 'delete_planner')
                    && ($hasViewAllData || ($isTeamlead && $this->permissionManager->hasRolePermission($user, 'edit_other_planner')))),

            default => false,
        };
    }
}
