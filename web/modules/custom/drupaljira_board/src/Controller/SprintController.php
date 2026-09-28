<?php

namespace Drupal\drupaljira_board\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides the Sprints page for Scrum projects.
 */
final class SprintController extends ControllerBase {

  /**
   * Displays the Sprints stub page.
   *
   * @return array
   *   A render array for the Sprints page.
   */
  public function page(): array {
    return [
      '#markup' => $this->t('Sprints functionality will be implemented in the next block.'),
    ];
  }

}
