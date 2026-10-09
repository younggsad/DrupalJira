<?php

namespace Drupal\drupaljira_board\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides access checking for the project Sprints section.
 */
final class SprintAccessCheck {

  /**
   * Checks access to the Sprints section of a project.
   *
   * Access is granted only when the node is a Project, the current user
   * has view access to the project, and the project type is Scrum.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The project node from the route parameter.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user's account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResult {
    if ($node->bundle() !== 'project') {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }

    $view_access = $node->access('view', $account, TRUE);
    if (!$view_access->isAllowed()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($view_access)
        ->addCacheableDependency($node);
    }

    $is_scrum = $node->get('field_project_type')->value === 'scrum';

    return AccessResult::allowedIf($is_scrum)
      ->addCacheableDependency($view_access)
      ->addCacheableDependency($node);
  }

}
