<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\DependencyInjection;

use KimaiPlugin\PlannerBundle\DependencyInjection\PlannerExtension;
use KimaiPlugin\PlannerBundle\EventSubscriber\MenuSubscriber;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PlannerExtensionTest extends TestCase
{
    public function testPrepend(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlannerExtension();

        $extension->prepend($container);

        $config = $container->getExtensionConfig('kimai');
        $this->assertCount(1, $config);

        $kimaiConfig = $config[0];
        $this->assertArrayHasKey('permissions', $kimaiConfig);
        $this->assertArrayNotHasKey('maps', $kimaiConfig['permissions']);

        $this->assertArrayHasKey('sets', $kimaiConfig['permissions']);
        $this->assertSame([
            'PLANNER' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
            ],
            'PLANNER_OTHER' => [
                'view_other_planner',
                'edit_other_planner',
            ],
        ], $kimaiConfig['permissions']['sets']);

        $this->assertArrayHasKey('roles', $kimaiConfig['permissions']);
        $this->assertSame([
            'ROLE_USER' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
            ],
            'ROLE_TEAMLEAD' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
                'view_other_planner',
                'edit_other_planner',
            ],
            'ROLE_ADMIN' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
                'view_other_planner',
                'edit_other_planner',
            ],
            'ROLE_SUPER_ADMIN' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
                'view_other_planner',
                'edit_other_planner',
            ],
        ], $kimaiConfig['permissions']['roles']);
    }

    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension = new PlannerExtension();
        $extension->load([], $container);

        $this->assertTrue($container->hasDefinition(WeeklyPlannerService::class));
        $this->assertTrue($container->hasDefinition(MenuSubscriber::class));
    }
}
