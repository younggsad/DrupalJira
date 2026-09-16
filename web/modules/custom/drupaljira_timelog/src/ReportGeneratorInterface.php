<?php

namespace Drupal\drupaljira_timelog;

use Drupal\node\NodeInterface;

/**
 * Defines the interface for report generator plugins.
 */
interface ReportGeneratorInterface {

  /**
   * Generates a report for a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project node.
   *
   * @return array
   *   The generated report data.
   */
  public function generate(NodeInterface $project): array;

  /**
   * Returns the human-readable report label.
   *
   * @return string
   *   The report label.
   */
  public function getLabel(): string;

}
