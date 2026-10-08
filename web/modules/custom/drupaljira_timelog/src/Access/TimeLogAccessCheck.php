<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Checks access to time logging and its referenced content.
 */
final class TimeLogAccessCheck {

  /**
   * Checks that a project is valid and viewable by the account.
   */
  public static function projectViewAccess(NodeInterface $node, AccountInterface $account): AccessResult {
    $access = AccessResult::allowedIf($node->bundle() === 'project' && !$node->isNew())
      ->addCacheableDependency($node);
    return $access->andIf($node->access('view', $account, TRUE));
  }

  /**
   * Checks a task and its parent project without discarding access metadata.
   */
  public static function taskViewAccess(NodeInterface $task, AccountInterface $account): AccessResult {
    $access = AccessResult::allowedIf($task->bundle() === 'task' && !$task->isNew())
      ->addCacheableDependency($task)
      ->andIf($task->access('view', $account, TRUE));
    if (!$task->hasField('field_project')) {
      return AccessResult::forbidden()->addCacheableDependency($access);
    }

    $field = $task->get('field_project');
    $access = $access->andIf($field->access('view', $account, TRUE));
    $project = $field->entity;
    if (!$project instanceof NodeInterface) {
      return AccessResult::forbidden()
        ->addCacheableDependency($access)
        ->addCacheTags(['node:' . $field->target_id]);
    }
    return $access->andIf(self::projectViewAccess($project, $account));
  }

  /**
   * Checks the permission to read time records and derived statistics.
   */
  public static function statisticsPermissionAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->addCacheContexts(['user.roles:authenticated'])
      ->andIf(AccessResult::allowedIfHasPermissions(
        $account, ['view time_log', 'administer time_log'], 'OR',
      ));
  }

  /**
   * Checks the task-specific time logging route.
   */
  public static function logTimeAccess(NodeInterface $task, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->addCacheContexts(['user.roles:authenticated'])
      ->andIf(AccessResult::allowedIfHasPermissions(
        $account, ['create time_log', 'administer time_log'], 'OR',
      ))
      ->andIf(self::taskViewAccess($task, $account));
  }

  /**
   * Checks the project statistics route.
   */
  public static function projectStatsAccess(NodeInterface $node, AccountInterface $account): AccessResult {
    return self::statisticsPermissionAccess($account)
      ->andIf(self::projectViewAccess($node, $account));
  }

}
