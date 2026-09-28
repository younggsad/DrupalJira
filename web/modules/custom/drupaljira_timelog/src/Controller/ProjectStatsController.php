<?php

namespace Drupal\drupaljira_timelog\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockManagerInterface;

/**
 * Controller for Project Statistics updates.
 */
final class ProjectStatsController extends ControllerBase {

  /**
   * Constructs a ProjectStatsController.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager.
   */
  public function __construct(
    protected BlockManagerInterface $blockManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
    $container->get('plugin.manager.block'),
    );
  }

  /**
   * Returns the updated Project Statistics.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The project node.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function getStats(NodeInterface $node): AjaxResponse {
    $block_plugin = $this->blockManager->createInstance('project_statistics', []);
    $render_array = $block_plugin->build();

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#project-statistics-wrapper', $render_array));

    return $response;
  }

}
