<?php

namespace Drupal\drupaljira_timelog\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
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

    return [
      '#theme' => 'drupaljira_project_stats',
      '#project' => $project,
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => $project->getCacheTags(),
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
