<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Form;

use App\Configuration\LocaleService;
use App\Entity\User;
use App\Form\Type\DatePickerType;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityApiForm;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlannedActivityApiFormTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $localeService = new LocaleService(['en' => [...LocaleService::DEFAULT_SETTINGS, 'translation' => true]]);

        return [
            new PreloadedExtension([
                new DatePickerType($localeService),
            ], [
                // the "documentation" option is provided by the API doc bundle in the real application
                FormType::class => [new class() extends AbstractTypeExtension {
                    public static function getExtendedTypes(): iterable
                    {
                        return [FormType::class];
                    }

                    public function configureOptions(OptionsResolver $resolver): void
                    {
                        $resolver->setDefault('documentation', []);
                    }
                }],
            ]),
        ];
    }

    public function testDisablesCsrfAndKeepsEditFormFields(): void
    {
        $builder = $this->factory->createBuilder(PlannedActivityApiForm::class, new PlannedActivity(), [
            'user' => new User(),
            'include_recurrence' => true,
        ]);

        $this->assertFalse($builder->getOption('csrf_protection'));
        $this->assertFalse($builder->has('user'));
        $this->assertFalse($builder->has('title'));
        foreach (['customer', 'project', 'begin', 'end', 'hoursPerDay', 'color', 'comment', 'recurrentWeeks'] as $field) {
            $this->assertTrue($builder->has($field), $field);
        }
    }

    public function testDatesUseIsoFormat(): void
    {
        $builder = $this->factory->createBuilder(PlannedActivityApiForm::class, new PlannedActivity(), ['user' => new User()]);

        $this->assertSame('yyyy-MM-dd', $builder->get('begin')->getOption('format'));
        $this->assertSame('yyyy-MM-dd', $builder->get('end')->getOption('format'));
    }
}
