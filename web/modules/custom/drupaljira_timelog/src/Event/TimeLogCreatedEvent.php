<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Event;

use Drupal\drupaljira_timelog\TimeLogInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a TimeLog entity is created.
 */
final class TimeLogCreatedEvent extends Event {

  /**
   * Constructs a new TimeLogCreatedEvent.
   *
   * @param \Drupal\drupaljira_timelog\TimeLogInterface $time_log
   *   The created time log entity.
   */
  public function __construct(
    private readonly TimeLogInterface $time_log,
  ) {}

  /**
   * Gets the created time log.
   *
   * @return \Drupal\drupaljira_timelog\TimeLogInterface
   *   The time log entity.
   */
  public function getTimeLog(): TimeLogInterface {
    return $this->time_log;
  }

}
