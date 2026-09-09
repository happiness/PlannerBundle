<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PlannerExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'sets' => [
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
                ],
                'maps' => [
                    'ROLE_USER' => ['PLANNER'],
                    'ROLE_TEAMLEAD' => ['PLANNER', 'PLANNER_OTHER'],
                    'ROLE_ADMIN' => ['PLANNER', 'PLANNER_OTHER'],
                    'ROLE_SUPER_ADMIN' => ['PLANNER', 'PLANNER_OTHER'],
                ],
            ],
        ]);
    }
}
