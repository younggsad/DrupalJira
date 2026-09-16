<?php

namespace Drupal\drupaljira_timelog\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupaljira_timelog\Service\TaskStatService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Project Statistics block.
 */
#[Block(
  id: 'project_statistics',
  admin_label: new TranslatableMarkup('Project Statistics'),
  category: new TranslatableMarkup('DrupalJira'),
)]
final class ProjectStatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectStatisticsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\drupaljira_timelog\Service\TaskStatService $taskStatService
   *   The task statistics service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected TaskStatService $taskStatService,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('drupaljira.task_stat'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $project = $this->getProjectFromRoute();

    if (!$project) {
      return [];
    }

    $stats = $this->taskStatService->getProjectStats($project);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['project-statistics'],
      ],
      'heading' => [
        '#markup' => '<h2>' . $this->t('Project Statistics') . '</h2>',
      ],
      'statistics' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Tasks: @count', [
            '@count' => $stats['task_count'],
          ]),
          $this->t('Completed tasks: @count', [
            '@count' => $stats['done_count'],
          ]),
          $this->t('Total estimate: @hours hours', [
            '@hours' => $this->formatHours($stats['total_estimate']),
          ]),
          $this->t('Total logged: @hours hours', [
            '@hours' => $this->formatHours($stats['total_logged']),
          ]),
          $this->t('Over estimate: @count', [
            '@count' => $stats['over_estimate_count'],
          ]),
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path'],
      ],
    ];
  }

  /**
   * Resolves the project from the current route.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The project node, or NULL if there is no project context.
   */
  protected function getProjectFromRoute(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    if ($node->bundle() === 'project') {
      return $node;
    }

    if ($node->bundle() !== 'task') {
      return NULL;
    }

    if ($node->get('field_project')->isEmpty()) {
      return NULL;
    }

    $project = $node->get('field_project')->entity;

    if (!$project instanceof NodeInterface || $project->bundle() !== 'project') {
      return NULL;
    }

    return $project;
  }

  /**
   * Formats a number of hours for display.
   *
   * @param float $hours
   *   The number of hours.
   *
   * @return string
   *   The formatted number.
   */
  protected function formatHours(float $hours): string {
    return rtrim(
      rtrim(number_format($hours, 2, '.', ''), '0'),
      '.',
    );
  }

}
