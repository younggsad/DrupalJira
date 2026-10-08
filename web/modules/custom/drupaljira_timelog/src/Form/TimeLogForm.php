<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\drupaljira_timelog\Access\TimeLogAccessCheck;
use Drupal\drupaljira_timelog\TimeLogInterface;
use Drupal\node\NodeInterface;

/**
 * Form controller for the time log entity edit forms.
 */
final class TimeLogForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);
    if ($entity instanceof TimeLogInterface && !$this->currentUser()->hasPermission('administer time_log')) {
      // Ignore forged owner input as well as hiding the owner field.
      $entity->setOwnerId($this->currentUser()->id());
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity = parent::validateForm($form, $form_state);
    $task = $entity->get('task')->entity;
    if (!$task instanceof NodeInterface || !TimeLogAccessCheck::taskViewAccess($task, $this->currentUser())->isAllowed()) {
      $form_state->setErrorByName('task', $this->t('Select a task and project you can access.'));
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    if (!$this->entity->isNew()) {
      // Authorize the stored record as well as the submitted references.
      $original = $this->entityTypeManager->getStorage('time_log')->loadUnchanged($this->entity->id());
      if (!$original) {
        throw new CacheableAccessDeniedHttpException($this->entity);
      }
      $original_access = $original->access('update', $this->currentUser(), TRUE);
      if (!$original_access->isAllowed()) {
        throw new CacheableAccessDeniedHttpException(CacheableMetadata::createFromObject($original_access));
      }
    }
    $access = $this->entity->isNew()
      ? $this->entityTypeManager->getAccessControlHandler('time_log')->createAccess(NULL, $this->currentUser(), [], TRUE)
      : $this->entity->access('update', $this->currentUser(), TRUE);
    $task = $this->entity->get('task')->entity;
    if (!$access->isAllowed()) {
      throw new CacheableAccessDeniedHttpException(CacheableMetadata::createFromObject($access));
    }
    if (!$task instanceof NodeInterface) {
      throw new CacheableAccessDeniedHttpException($this->entity);
    }
    $task_access = TimeLogAccessCheck::taskViewAccess($task, $this->currentUser());
    if (!$task_access->isAllowed()) {
      throw new CacheableAccessDeniedHttpException($task_access);
    }
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

    $form_state->setRedirectUrl($this->currentUser()->hasPermission('administer time_log')
      ? $this->entity->toUrl('collection') : $task->toUrl());

    return $result;
  }

}
