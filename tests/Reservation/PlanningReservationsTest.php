<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Reservation;

use KimaiPlugin\PlannerBundle\Reservation\NoPlanningReservations;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservation;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservationProviderInterface;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservations;
use PHPUnit\Framework\TestCase;

class PlanningReservationsTest extends TestCase
{
    /**
     * @param array<PlanningReservation> $reservations
     */
    public static function create(array $reservations): PlanningReservations
    {
        return new PlanningReservations(new class($reservations) implements PlanningReservationProviderInterface {
            /**
             * @param array<PlanningReservation> $reservations
             */
            public function __construct(private readonly array $reservations)
            {
            }

            public function getReservations(): array
            {
                return $this->reservations;
            }
        });
    }

    public function testTheDefaultProviderHasNone(): void
    {
        $reservations = new PlanningReservations(new NoPlanningReservations());

        $this->assertSame([], $reservations->all());
        $this->assertSame([], $reservations->getSelectable());
        $this->assertNull($reservations->get('buffer'));
        $this->assertFalse($reservations->isSelectable('buffer'));
    }

    public function testLookups(): void
    {
        $buffer = new PlanningReservation('buffer', 'Agency buffer', '#ff0000');
        $lead = new PlanningReservation('lead', 'Tech leading');
        $old = new PlanningReservation('old', 'Old', null, true);
        $reservations = self::create([$buffer, $old, $lead, new PlanningReservation('buffer', 'Duplicate')]);

        $this->assertSame(['buffer' => $buffer, 'old' => $old, 'lead' => $lead], $reservations->all());
        $this->assertSame(['buffer' => $buffer, 'lead' => $lead], $reservations->getSelectable());
        $this->assertSame($buffer, $reservations->get('buffer'));
        $this->assertNull($reservations->get(null));
        $this->assertTrue($reservations->isSelectable('lead'));
        $this->assertFalse($reservations->isSelectable('old'));
        $this->assertFalse($reservations->isSelectable('unknown'));
        $this->assertTrue($reservations->get('old')?->isArchived());
        $this->assertSame(0, $reservations->getPosition('buffer'));
        $this->assertSame(2, $reservations->getPosition('lead'));
        $this->assertSame(\PHP_INT_MAX, $reservations->getPosition('unknown'));
    }

    public function testTheProviderIsAskedOnce(): void
    {
        $provider = $this->createMock(PlanningReservationProviderInterface::class);
        $provider->expects($this->once())->method('getReservations')->willReturn([]);
        $reservations = new PlanningReservations($provider);

        $reservations->all();
        $reservations->getSelectable();
        $reservations->get('x');
    }

    public function testColourFallsBackToOneDerivedFromTheName(): void
    {
        $this->assertSame('#123456', (new PlanningReservation('a', 'A', '#123456'))->getColorSafe());
        $this->assertSame((new PlanningReservation('b', 'Same'))->getColorSafe(), (new PlanningReservation('c', 'Same'))->getColorSafe());
    }

    public function testKeyMustFitTheColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PlanningReservation(str_repeat('x', 65), 'Too long');
    }

    public function testKeysThatLookLikeNumbersStayStrings(): void
    {
        // PHP makes integer array keys of them, which must not leak out as integers
        $reservations = self::create([
            new PlanningReservation('42', 'Forty-two'),
            new PlanningReservation('buffer', 'Buffer'),
            new PlanningReservation('7', 'Seven', null, true),
            new PlanningReservation('old', 'Old', null, true),
        ]);

        $this->assertSame(0, $reservations->getPosition('42'));
        $this->assertSame(1, $reservations->getPosition('buffer'));
        $this->assertTrue($reservations->isSelectable('42'));
        $this->assertFalse($reservations->isSelectable('7'));
    }
}
