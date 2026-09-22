<?php

namespace Drupal\drupaljira_timelog\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\drupaljira_timelog\Service\DurationFormatter;
use Drupal\drupaljira_timelog\Service\TaskStatService;

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
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\drupaljira_timelog\Service\TaskStatService $taskStatService
   *   The task statistics service.
   * @param \Drupal\drupaljira_timelog\Service\DurationFormatter $durationFormatter
   *   The duration formatter service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected TaskStatService $taskStatService,
    protected DurationFormatter $durationFormatter,
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
      $container->get('current_route_match'),
      $container->get('drupaljira.task_stat'),
      $container->get('drupaljira_timelog.duration_formatter'),
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

    $totalEstimate = (float) $stats['total_estimate'];
    $totalLogged = (float) $stats['total_logged'];
    $remaining = (float) $stats['remaining_hours'];

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'project-statistics-wrapper',
      ],
      'content' => [
        '#theme' => 'drupaljira_project_stats',
        '#project' => $project,
        '#project_name' => $project->label(),
        '#total_estimate' => $this->t('Total pledged: @hours', [
          '@hours' => $this->durationFormatter->format($totalEstimate),
        ]),
        '#total_logged' => $this->t('Total logged: @hours', [
          '@hours' => $this->durationFormatter->format($totalLogged),
        ]),
        '#remaining_hours' => $this->t('Remaining: @hours', [
          '@hours' => $this->durationFormatter->format($remaining),
        ]),
        '#tasks_summary' => $this->t('Completed tasks: @done of @total', [
          '@done' => $stats['done_count'],
          '@total' => $stats['task_count'],
        ]),
        '#over_estimate_count' => $this->t(
          'Tasks over estimate: @count',
          [
            '@count' => $stats['over_estimate_count'],
          ],
        ),
      ],
      'refresh_link' => [
        '#type' => 'link',
        '#title' => $this->t('Refresh statistics'),
        '#url' => Url::fromRoute('drupaljira_timelog.project_stats', ['node' => $project->id()]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => [
          'drupaljira_project_stats:' . $project->id(),
        ],
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

}
