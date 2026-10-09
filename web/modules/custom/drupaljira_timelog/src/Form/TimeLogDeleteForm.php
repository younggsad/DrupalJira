<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;
use Drupal\drupaljira_timelog\TimeLogInterface;
use Drupal\node\NodeInterface;

/**
 * Returns owners to their task rather than the administrative collection.
 */
final class TimeLogDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    if ($this->currentUser()->hasPermission('administer time_log')) {
      return parent::getCancelUrl();
    }
    $entity = $this->getEntity();
    $task = $entity instanceof TimeLogInterface ? $entity->get('task')->entity : NULL;
    return $task instanceof NodeInterface ? $task->toUrl() : Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    return $this->getCancelUrl();
  }

}
