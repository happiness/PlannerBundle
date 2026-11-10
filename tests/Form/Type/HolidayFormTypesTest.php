<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Form\Type;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarPreferenceType;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarType;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayObservancesType;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class HolidayFormTypesTest extends TypeTestCase
{
    private string $storedObservances = '';

    protected function getExtensions(): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('en');
        $translator->method('trans')->willReturnArgument(0);

        $loader = $this->createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);
        $configuration = new SystemConfiguration($loader, [HolidayCalendars::SETTING_OBSERVANCES => $this->storedObservances]);

        $calendars = new HolidayCalendars();

        return [new PreloadedExtension([
            new HolidayCalendarType($calendars, $translator),
            new HolidayObservancesType($configuration, $calendars, $translator),
        ], [])];
    }

    public function testCalendarInSettings(): void
    {
        $form = $this->factory->create(HolidayCalendarType::class);
        $view = $form->createView();

        $this->assertSame('planner.holidays.none', $view->vars['placeholder']);
        $values = array_map(static fn ($choice) => $choice->value, $view->vars['choices']);
        $this->assertContains('SE', $values);
        $this->assertNotContains(HolidayCalendars::NONE, $values);

        $form->submit('GB-ENG');
        $this->assertTrue($form->isValid());
        $this->assertSame('GB-ENG', $form->getData());

        $form = $this->factory->create(HolidayCalendarType::class);
        $form->submit('');
        $this->assertNull($form->getData());
    }

    public function testCalendarOnAUserCanOptOut(): void
    {
        $form = $this->factory->create(HolidayCalendarPreferenceType::class);
        $view = $form->createView();

        $this->assertSame('planner.holidays.organisation_default', $view->vars['placeholder']);
        $this->assertSame(HolidayCalendars::NONE, $view->vars['choices'][0]->value);
        $this->assertSame('planner.holidays.none', $view->vars['choices'][0]->label);

        $form->submit(HolidayCalendars::NONE);
        $this->assertSame(HolidayCalendars::NONE, $form->getData());
    }

    public function testUnknownCalendarIsInvalid(): void
    {
        $form = $this->factory->create(HolidayCalendarType::class);
        $form->submit('XX');

        $this->assertFalse($form->isSynchronized());
    }

    public function testObservancesAreStoredAsCsv(): void
    {
        $form = $this->factory->create(HolidayObservancesType::class, 'christmasEve');
        $this->assertSame(['christmasEve'], $form->getViewData());

        $form->submit(['christmasEve', 'stJohnsEve']);
        $this->assertSame('christmasEve,stJohnsEve', $form->getData());

        // nothing ticked deletes the setting
        $form = $this->factory->create(HolidayObservancesType::class, 'christmasEve');
        $form->submit([]);
        $this->assertNull($form->getData());
    }

    public function testStoredObservancesOutsideTheChoicesSurviveASave(): void
    {
        $this->storedObservances = 'christmasEve,someFutureEve';
        $this->setUp();

        $form = $this->factory->create(HolidayObservancesType::class, $this->storedObservances);
        $form->submit(['christmasEve', 'someFutureEve']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('christmasEve,someFutureEve', $form->getData());
    }
}
