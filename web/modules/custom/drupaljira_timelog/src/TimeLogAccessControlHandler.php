<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupaljira_timelog\Access\TimeLogAccessCheck;
use Drupal\node\NodeInterface;

/**
 * Defines the access control handler for the time log entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class TimeLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if (!$entity instanceof TimeLogInterface || !$account->isAuthenticated()) {
      return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($entity);
    }

    $admin = $account->hasPermission('administer time_log');
    $permission = match ($operation) {
      'view' => 'view time_log',
      'update' => 'edit time_log',
      'delete' => 'delete time_log',
      default => NULL,
    };
    $access = AccessResult::allowedIf($permission !== NULL && ($admin || $account->hasPermission($permission)))
      ->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
    if (in_array($operation, ['update', 'delete'], TRUE) && !$admin) {
      $access = $access->andIf(AccessResult::allowedIf((string) $entity->getOwnerId() === (string) $account->id())
        ->cachePerUser()->addCacheableDependency($entity));
    }

    $task = $entity->get('task')->entity;
    if (!$task instanceof NodeInterface) {
      return AccessResult::forbidden()->addCacheableDependency($access)
        ->addCacheTags(['node:' . $entity->get('task')->target_id]);
    }
    return $access->andIf(TimeLogAccessCheck::taskViewAccess($task, $account));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->addCacheContexts(['user.roles:authenticated'])
      ->andIf(AccessResult::allowedIfHasPermissions($account, ['create time_log', 'administer time_log'], 'OR'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL) {
    if ($operation === 'edit' && $field_definition->getName() === 'uid') {
      return AccessResult::allowedIfHasPermission($account, 'administer time_log');
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
