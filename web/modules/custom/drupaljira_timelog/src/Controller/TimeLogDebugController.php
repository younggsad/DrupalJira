<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupaljira_timelog\TimeLogInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides debug pages for the TimeLog entity API.
 */
final class TimeLogDebugController extends ControllerBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $timeLogEntityTypeManager;

  /**
   * Constructs a TimeLogDebugController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->timeLogEntityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Demonstrates the complete TimeLog CRUD lifecycle.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   *
   * @return array
   *   A render array containing CRUD operation results.
   */
  public function crud(NodeInterface $task): array {
    $storage = $this->timeLogEntityTypeManager->getStorage('time_log');

    // Create.
    $time_log = $storage->create([
      'task' => $task->id(),
      'uid' => $this->currentUser()->id(),
      'hours' => '2.00',
      'log_date' => date('Y-m-d'),
      'notes' => 'CRUD debug test.',
    ]);
    $time_log->save();

    $id = $time_log->id();

    // Load.
    $loaded = $storage->load($id);

    if (!$loaded instanceof TimeLogInterface) {
      return [
        '#markup' => $this->t('Failed to load TimeLog @id.', [
          '@id' => $id,
        ]),
      ];
    }

    $loaded_hours = $loaded->get('hours')->value;

    // Update.
    $loaded->set('hours', '3.00');
    $loaded->save();

    $updated_hours = $loaded->get('hours')->value;

    // Delete.
    $loaded->delete();

    // Verify deletion.
    $deleted = $storage->load($id) === NULL;

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('TimeLog CRUD debug'),
      '#items' => [
        $this->t('Created TimeLog ID: @id', [
          '@id' => $id,
        ]),
        $this->t('Loaded hours: @hours', [
          '@hours' => $loaded_hours,
        ]),
        $this->t('Updated hours: @hours', [
          '@hours' => $updated_hours,
        ]),
        $this->t('Deleted TimeLog ID: @id', [
          '@id' => $id,
        ]),
        $this->t('Record no longer exists: @result', [
          '@result' => $deleted ? 'yes' : 'no',
        ]),
      ],
    ];
  }

  /**
   * Lists TimeLog records for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   *
   * @return array
   *   A render array containing the TimeLog table.
   */
  public function list(NodeInterface $task): array {
    $storage = $this->timeLogEntityTypeManager->getStorage('time_log');

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('task', $task->id())
      ->sort('log_date', 'ASC')
      ->execute();

    $entities = $storage->loadMultiple($ids);

    $rows = [];

    foreach ($entities as $time_log) {
      if (!$time_log instanceof TimeLogInterface) {
        continue;
      }

      $rows[] = [
        'id' => $time_log->id(),
        'hours' => $time_log->get('hours')->value,
        'log_date' => $time_log->get('log_date')->value,
      ];
    }

    return [
      '#theme' => 'table',
      '#header' => [
        'id' => $this->t('ID'),
        'hours' => $this->t('Hours'),
        'log_date' => $this->t('Log date'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No TimeLog records found for this task.'),
    ];
  }

  /**
   * Displays the total hours logged for a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node.
   *
   * @return array
   *   A render array containing the total hours.
   */
  public function sum(NodeInterface $task): array {
    $storage = $this->timeLogEntityTypeManager->getStorage('time_log');

    $query = $storage->getAggregateQuery()
      ->accessCheck(TRUE)
      ->condition('task', $task->id())
      ->aggregate('hours', 'SUM');

    $result = $query->execute();

    $total = 0.0;

    if ($result) {
      $total = (float) reset($result);
    }

    return [
      '#markup' => $this->t('Total hours: @hours', [
        '@hours' => number_format($total, 2, '.', ''),
      ]),
    ];
  }

}
