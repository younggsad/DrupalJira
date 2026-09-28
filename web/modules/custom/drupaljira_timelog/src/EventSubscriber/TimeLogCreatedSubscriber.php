<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\drupaljira_timelog\Event\TimeLogCreatedEvent;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles TimeLog creation events.
 */
final class TimeLogCreatedSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TimeLogCreatedSubscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    private readonly LoggerChannelFactoryInterface $logger_factory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TimeLogCreatedEvent::class => 'onTimeLogCreated',
    ];
  }

  /**
   * Handles a TimeLog creation event.
   *
   * @param \Drupal\drupaljira_timelog\Event\TimeLogCreatedEvent $event
   *   The TimeLog creation event.
   */
  public function onTimeLogCreated(TimeLogCreatedEvent $event): void {
    $time_log = $event->getTimeLog();
    $task = $time_log->get('task')->entity;
    $user = $time_log->getOwner();

    if ($task instanceof NodeInterface) {
      $project = $task->get('field_project')->entity;

      if ($project instanceof NodeInterface) {
        Cache::invalidateTags([
          'drupaljira_project_stats:' . $project->id(),
        ]);
      }
    }

    $this->logger_factory
      ->get('drupaljira_timelog')
      ->notice(
        'Time logged: user @user (@uid), @hours hours, task @task.',
        [
          '@user' => $user->getDisplayName(),
          '@uid' => $time_log->getOwnerId(),
          '@hours' => $time_log->get('hours')->value,
          '@task' => $task instanceof NodeInterface
            ? $task->id()
            : 'Unknown',
        ]
      );
  }

}
