<?php

namespace Drupal\drupaljira_timelog\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides statistics for tasks and projects.
 */
class TaskStatService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a TaskStatService object.
   */
  public function __construct() {
    // Dependency injection is introduced in the next task.
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Returns the total logged hours for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   *
   * @return float
   *   The total logged hours.
   */
  public function getLoggedHours(NodeInterface $task): float {
    $time_log_storage = $this->entityTypeManager->getStorage('time_log');

    $time_log_ids = $time_log_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('task', $task->id())
      ->execute();

    if (!$time_log_ids) {
      return 0.0;
    }

    /** @var \Drupal\drupaljira_timelog\TimeLogInterface[] $time_logs */
    $time_logs = $time_log_storage->loadMultiple($time_log_ids);

    $total_logged = 0.0;

    foreach ($time_logs as $time_log) {
      $total_logged += (float) $time_log->get('hours')->value;
    }

    return $total_logged;
  }

  /**
   * Returns the remaining estimate for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   *
   * @return float
   *   The remaining estimate. A negative value means the estimate was exceeded.
   */
  public function getRemainingEstimate(NodeInterface $task): float {
    $estimate = (float) $task->get('field_estimate')->value;

    return $estimate - $this->getLoggedHours($task);
  }

  /**
   * Returns aggregated statistics for a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project node.
   *
   * @return array
   *   The project statistics.
   */
  public function getProjectStats(NodeInterface $project): array {
    $task_storage = $this->entityTypeManager->getStorage('node');

    $task_ids = $task_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'task')
      ->condition('field_project', $project->id())
      ->execute();

    $tasks = $task_storage->loadMultiple($task_ids);

    $task_count = count($tasks);
    $done_count = 0;
    $total_estimate = 0.0;
    $total_logged = 0.0;
    $over_estimate_count = 0;

    foreach ($tasks as $task) {
      if ($task->get('field_status')->value === 'done') {
        $done_count++;
      }

      $total_estimate += (float) $task->get('field_estimate')->value;

      $logged_hours = $this->getLoggedHours($task);
      $total_logged += $logged_hours;

      if ($this->getRemainingEstimate($task) < 0) {
        $over_estimate_count++;
      }
    }

    return [
      'task_count' => $task_count,
      'done_count' => $done_count,
      'total_estimate' => $total_estimate,
      'total_logged' => $total_logged,
      'over_estimate_count' => $over_estimate_count,
    ];
  }

}
