<?php

namespace Drupal\drupaljira_timelog\Plugin\ReportGenerator;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupaljira_timelog\Attribute\ReportGenerator;
use Drupal\drupaljira_timelog\ReportGeneratorInterface;
use Drupal\drupaljira_timelog\Service\TaskStatService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates a project summary report.
 */
#[ReportGenerator(
  id: 'project_summary',
  label: 'Project Summary',
)]
final class ProjectSummaryReport implements
  ReportGeneratorInterface,
  ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectSummaryReport plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\drupaljira_timelog\Service\TaskStatService $taskStatService
   *   The task statistics service.
   */
  public function __construct(
    protected array $configuration,
    protected string $plugin_id,
    protected mixed $plugin_definition,
    protected TaskStatService $taskStatService,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(NodeInterface $project): array {
    return $this->taskStatService->getProjectStats($project);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'Project Summary';
  }

}
