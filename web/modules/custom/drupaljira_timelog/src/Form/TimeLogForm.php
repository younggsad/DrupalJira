<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the time log entity edit forms.
 */
final class TimeLogForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $id = $this->entity->id();
    $message_args = ['%id' => $id];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New time log #%id has been created.', $message_args));
        $this->logger('drupaljira_timelog')->notice('New time log #%id has been created.', $message_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The time log #%id has been updated.', $message_args));
        $this->logger('drupaljira_timelog')->notice('The time log #%id has been updated.', $message_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
