<?php

namespace Drupal\drupaljira_timelog\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drupaljira_timelog\Access\TimeLogAccessCheck;
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
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $account,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Returns cacheable access to a task's time statistics.
   */
  public function getTaskAccess(NodeInterface $task): AccessResult {
    return TimeLogAccessCheck::statisticsPermissionAccess($this->account)
      ->andIf(TimeLogAccessCheck::taskViewAccess($task, $this->account));
  }

  /**
   * Returns cacheable access to a project's time statistics.
   */
  public function getProjectAccess(NodeInterface $project): AccessResult {
    return TimeLogAccessCheck::projectStatsAccess($project, $this->account);
  }

  /**
   * Returns the total logged hours for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional cache metadata carrier for the statistics.
   *
   * @return float
   *   The total logged hours.
   */
  public function getLoggedHours(NodeInterface $task, ?CacheableMetadata $cache = NULL): float {
    $access = $this->getTaskAccess($task);
    $cache?->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      throw new CacheableAccessDeniedHttpException($access);
    }
    $time_log_storage = $this->entityTypeManager->getStorage('time_log');

    $time_log_ids = $time_log_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('task', $task->id())
      ->execute();

    if (!$time_log_ids) {
      return 0.0;
    }

    /** @var \Drupal\drupaljira_timelog\TimeLogInterface[] $time_logs */
    $time_logs = $time_log_storage->loadMultiple($time_log_ids);

    $total_logged = 0.0;

    foreach ($time_logs as $time_log) {
      $log_access = $time_log->access('view', $this->account, TRUE);
      $cache?->addCacheableDependency($log_access);
      if ($log_access->isAllowed()) {
        $total_logged += (float) $time_log->get('hours')->value;
      }
    }

    return $total_logged;
  }

  /**
   * Returns the remaining estimate for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional cache metadata carrier for the statistics.
   *
   * @return float
   *   The remaining estimate. A negative value means the estimate was exceeded.
   */
  public function getRemainingEstimate(NodeInterface $task, ?CacheableMetadata $cache = NULL): float {
    $logged = $this->getLoggedHours($task, $cache);
    $estimate = (float) $task->get('field_estimate')->value;

    return $estimate - $logged;
  }

  /**
   * Returns aggregated statistics for a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project node.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional cache metadata carrier for the statistics.
   *
   * @return array
   *   The project statistics.
   */
  public function getProjectStats(NodeInterface $project, ?CacheableMetadata $cache = NULL): array {
    $access = $this->getProjectAccess($project);
    $cache?->addCacheableDependency($access);
    $cache?->addCacheContexts(['user', 'user.permissions', 'user.node_grants:view']);
    $cache?->addCacheTags(['node_list:task', 'time_log_list']);
    if (!$access->isAllowed()) {
      throw new CacheableAccessDeniedHttpException($access);
    }
    $task_storage = $this->entityTypeManager->getStorage('node');

    $task_ids = $task_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'task')
      ->condition('field_project', $project->id())
      ->execute();

    /** @var NodeInterface[] $tasks */
    $tasks = $task_storage->loadMultiple($task_ids);

    // Filter once, then load all logs for the accessible tasks in one batch.
    foreach ($tasks as $id => $task) {
      $task_access = $this->getTaskAccess($task);
      $cache?->addCacheableDependency($task_access);
      if (!$task_access->isAllowed()) {
        unset($tasks[$id]);
      }
    }
    $logged_by_task = [];
    if ($tasks) {
      $time_log_storage = $this->entityTypeManager->getStorage('time_log');
      $time_log_ids = $time_log_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('task', array_keys($tasks), 'IN')
        ->execute();
      /** @var \Drupal\drupaljira_timelog\TimeLogInterface[] $time_logs */
      $time_logs = $time_log_storage->loadMultiple($time_log_ids);
      foreach ($time_logs as $time_log) {
        $log_access = $time_log->access('view', $this->account, TRUE);
        $cache?->addCacheableDependency($log_access);
        if ($log_access->isAllowed()) {
          $task_id = $time_log->get('task')->target_id;
          $logged_by_task[$task_id] = ($logged_by_task[$task_id] ?? 0.0)
            + (float) $time_log->get('hours')->value;
        }
      }
    }

    $task_count = 0;
    $done_count = 0;
    $total_estimate = 0.0;
    $total_logged = 0.0;
    $over_estimate_count = 0;

    foreach ($tasks as $task) {
      $task_count++;
      if ($task->get('field_status')->value === 'done') {
        $done_count++;
      }

      $estimate = (float) $task->get('field_estimate')->value;
      $total_estimate += $estimate;

      $logged_hours = $logged_by_task[$task->id()] ?? 0.0;
      $total_logged += $logged_hours;

      if ($estimate - $logged_hours < 0) {
        $over_estimate_count++;
      }
    }

    return [
      'task_count' => $task_count,
      'done_count' => $done_count,
      'total_estimate' => $total_estimate,
      'total_logged' => $total_logged,
      'remaining_hours' => $total_estimate - $total_logged,
      'over_estimate_count' => $over_estimate_count,
    ];
  }

}
