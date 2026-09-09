<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Entity;

use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use PHPUnit\Framework\TestCase;

class PlannedActivityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $activity = new PlannedActivity();

        $this->assertNull($activity->getId());

        $user = new User();
        $this->assertSame($activity, $activity->setUser($user));
        $this->assertSame($user, $activity->getUser());

        $begin = new \DateTime('2026-09-01');
        $this->assertSame($activity, $activity->setBegin($begin));
        $this->assertSame($begin, $activity->getBegin());

        $end = new \DateTime('2026-09-05');
        $this->assertSame($activity, $activity->setEnd($end));
        $this->assertSame($end, $activity->getEnd());

        $this->assertSame($activity, $activity->setTitle('Sprint Planning'));
        $this->assertSame('Sprint Planning', $activity->getTitle());
        $this->assertSame('Sprint Planning', $activity->getName());

        $this->assertSame($activity, $activity->setHoursPerDay(4.5));
        $this->assertSame(4.5, $activity->getHoursPerDay());

        $activity->setColor('#123456');
        $this->assertSame('#123456', $activity->getColor());

        $this->assertSame($activity, $activity->setComment('Important tasks'));
        $this->assertSame('Important tasks', $activity->getComment());
    }

    public function testCoversDateAndHoursCalculation(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-01'));
        $activity->setEnd(new \DateTime('2026-09-05'));
        $activity->setHoursPerDay(6.0);

        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-01')));
        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-03')));
        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-05')));
        $this->assertFalse($activity->coversDate(new \DateTime('2026-08-31')));
        $this->assertFalse($activity->coversDate(new \DateTime('2026-09-06')));

        $this->assertSame(5, $activity->getDaysCount());
        $this->assertSame(30.0, $activity->getTotalHours());
    }
}
