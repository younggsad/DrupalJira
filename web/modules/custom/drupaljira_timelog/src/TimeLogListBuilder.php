<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a list controller for the time log entity type.
 */
final class TimeLogListBuilder extends EntityListBuilder {

  /**
   * Cacheability of allowed and denied records in the current listing.
   */
  private ?CacheableMetadata $accessMetadata = NULL;

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    // Custom entity queries do not implement per-record access filtering.
    $this->accessMetadata = new CacheableMetadata();
    $this->accessMetadata->addCacheContexts(['user', 'user.permissions', 'user.node_grants:view']);
    $this->accessMetadata->addCacheTags(['node_list']);
    $entities = [];
    foreach (parent::load() as $id => $entity) {
      $access = $entity->access('view', NULL, TRUE);
      $this->accessMetadata->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $entities[$id] = $entity;
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    if ($this->accessMetadata) {
      CacheableMetadata::createFromRenderArray($build)->merge($this->accessMetadata)->applyTo($build);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['task'] = $this->t('Task');
    $header['uid'] = $this->t('User');
    $header['hours'] = $this->t('Hours');
    $header['log_date'] = $this->t('Log Date');
    $header['notes'] = $this->t('Notes');
    $header['over_estimate_reason'] = $this->t('Over Estimate Reason');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\drupaljira_timelog\Entity\TimeLog $entity */
    $row['id'] = $entity->id();

    // Получаем привязанную задачу (Node) и выводим заголовок.
    $task = $entity->get('task')->entity;
    $row['task'] = $task ? $task->label() : '';

    // Получаем пользователя и выводим имя.
    $user = $entity->get('uid')->entity;
    $row['uid'] = $user ? $user->label() : '';

    // Выводим значение часов.
    $row['hours'] = $entity->get('hours')->value;

    // Выводим дату.
    $row['log_date'] = $entity->get('log_date')->value;

    // Выводим заметку.
    $row['notes'] = $entity->get('notes')->value;

    // Выводим причину превышения оценки.
    $row['over_estimate_reason'] = $entity->get('over_estimate_reason')->value;

    return $row + parent::buildRow($entity);
  }

}
