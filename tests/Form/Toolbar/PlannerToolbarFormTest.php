<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Form\Toolbar;

use App\Form\Type\TeamType;
use App\Form\Type\UserType;
use KimaiPlugin\PlannerBundle\Form\Toolbar\PlannerToolbarForm;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlannerToolbarFormTest extends TestCase
{
    public function testConfigureOptions(): void
    {
        $formType = new PlannerToolbarForm();
        $resolver = new OptionsResolver();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(PlannerQuery::class, $options['data_class']);
        self::assertFalse($options['csrf_protection']);
        self::assertSame('GET', $options['method']);
    }

    public function testBuildForm(): void
    {
        $formType = new PlannerToolbarForm();
        $builder = $this->createMock(FormBuilderInterface::class);

        /** @var array<string, array{type: string, options: array<string, mixed>}> $fields */
        $fields = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$fields, $builder) {
            $fields[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        $formType->buildForm($builder, []);

        self::assertArrayHasKey('users', $fields);
        self::assertArrayHasKey('teams', $fields);
        self::assertArrayHasKey('page', $fields);
        self::assertSame(UserType::class, $fields['users']['type']);
        self::assertSame(TeamType::class, $fields['teams']['type']);
        self::assertTrue($fields['users']['options']['multiple']);
        self::assertFalse($fields['users']['options']['required']);
        self::assertTrue($fields['teams']['options']['multiple']);
        self::assertFalse($fields['teams']['options']['required']);
    }
}
