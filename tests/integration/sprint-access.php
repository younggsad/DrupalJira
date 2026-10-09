<?php

/**
 * @file
 * Checks sprint access/cacheability with unsaved nodes via drush php:script.
 */

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\drupaljira_board\Access\SprintAccessCheck;
use Drupal\node\Entity\Node;

/**
 * Supplies controlled entity access metadata without saving test content.
 */
$new_node = static function (string $bundle) {
  return new class([], 'node', $bundle) extends Node {

    /**
     * The view access result.
     */
    public AccessResultInterface $viewAccess;

    /**
     * {@inheritdoc}
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The controlled access result.
     */
    public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE) {
      if ($operation !== 'view' || !$return_as_object) {
        throw new RuntimeException('Expected cacheable view access.');
      }
      return $this->viewAccess;
    }

  };
};

$checker = new SprintAccessCheck();
$account = new AnonymousUserSession();
$checks = 0;
foreach (['scrum', 'kanban'] as $type) {
  foreach (['allowed', 'forbidden', 'neutral'] as $decision) {
    foreach ([Cache::PERMANENT, 120, 0] as $max_age) {
      $node = $new_node('project');
      $node->set('nid', 123);
      $node->set('field_project_type', $type);
      $node->viewAccess = AccessResult::$decision()
        ->addCacheContexts(['user.permissions', 'user.node_grants:view'])
        ->addCacheTags(['sprint_access_test'])
        ->setCacheMaxAge($max_age);
      $result = $checker->access($node, $account);
      if ($result->isAllowed() !== ($type === 'scrum' && $decision === 'allowed')
        || array_diff($node->viewAccess->getCacheContexts(), $result->getCacheContexts())
        || array_diff(array_merge($node->getCacheTags(), $node->viewAccess->getCacheTags()), $result->getCacheTags())
        || $result->getCacheMaxAge() !== $max_age
        || ($decision !== 'allowed' && !$result->isForbidden())) {
        throw new RuntimeException("Failed $type/$decision/$max_age");
      }
      $checks++;
    }
  }
}
$task = $new_node('task');
$task->set('nid', 456);
$result = $checker->access($task, $account);
if (!$result->isForbidden() || array_diff($task->getCacheTags(), $result->getCacheTags())) {
  throw new RuntimeException('Non-project access must be forbidden and cacheable.');
}
print 'PASS: ' . ($checks + 1) . " sprint access/cacheability cases.\n";
