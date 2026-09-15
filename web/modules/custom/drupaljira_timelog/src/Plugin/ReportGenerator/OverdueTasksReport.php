<?php

namespace Drupal\drupaljira_timelog\Plugin\ReportGenerator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupaljira_timelog\Attribute\ReportGenerator;
use Drupal\drupaljira_timelog\ReportGeneratorInterface;
use Drupal\drupaljira_timelog\Service\TaskStatService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates a report of tasks that exceeded their estimates.
 */
#[ReportGenerator(
  id: 'overdue_tasks',
  label: 'Overdue Tasks',
)]
final class OverdueTasksReport implements
  ReportGeneratorInterface,
  ContainerFactoryPluginInterface {

  /**
   * Constructs an OverdueTasksReport plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\drupaljira_timelog\Service\TaskStatService $taskStatService
   *   The task statistics service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected TaskStatService $taskStatService,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupaljira_timelog.task_stat_service'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(NodeInterface $project): array {
    $task_storage = $this->entityTypeManager->getStorage('node');

    $task_ids = $task_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'task')
      ->condition('field_project', $project->id())
      ->execute();

    if (!$task_ids) {
      return [];
    }

    $tasks = $task_storage->loadMultiple($task_ids);
    $overdue_tasks = [];

    foreach ($tasks as $task) {
      $remaining_estimate = $this->taskStatService->getRemainingEstimate($task);

      if ($remaining_estimate >= 0) {
        continue;
      }

      $overdue_tasks[] = [
        'id' => (int) $task->id(),
        'title' => $task->label(),
        'overestimation' => abs($remaining_estimate),
      ];
    }

    return $overdue_tasks;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'Overdue Tasks';
  }

}
