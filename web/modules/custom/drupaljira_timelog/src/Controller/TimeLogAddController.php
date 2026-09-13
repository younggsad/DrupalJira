<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\drupaljira_timelog\Entity\TimeLog;

/**
 * Controller for building the Time Log add form.
 */
final class TimeLogAddController extends ControllerBase {

  /**
   * Builds the time log add form.
   */
  public function add(): array {
    $entity = TimeLog::create();
    return $this->entityFormBuilder()->getForm($entity, 'add');
  }

}
