<?php

/**
 * @file
 * Post-update functions for DrupalJira TimeLog.
 */

use Drupal\Core\Utility\UpdateException;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Reconcile Task workflow fields while retaining the original revisions.
 */
function drupaljira_timelog_post_update_reconcile_task_workflow(&$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $workflow = Workflow::load('task_workflow');
  $field = FieldStorageConfig::load('node.field_status');
  if (!$workflow || !$field || !in_array('task', $workflow->getTypePlugin()->getConfiguration()['entity_types']['node'] ?? [], TRUE)) {
    throw new UpdateException('Task workflow or status field configuration is missing.');
  }
  /** @var \Drupal\content_moderation\ContentModerationState[] $states */
  $states = $workflow->getTypePlugin()->getStates();
  $map = [
    'todo' => 'backlog',
    'working' => 'in_progress',
    'qa' => 'review',
    'completed' => 'done',
  ];
  foreach (array_keys($states) as $id) {
    $map[$id] = $id;
  }
  foreach (['backlog', 'in_progress', 'review', 'done'] as $id) {
    if (!isset($states[$id])) {
      throw new UpdateException("Required Task workflow state '$id' is missing.");
    }
  }

  // Preflight every current translation before writing any entity. Historical
  // revisions remain untouched; unknown values require an explicit decision.
  $resolve = static function (NodeInterface $task) use ($map, $states): string {
    $status = $task->get('field_status')->value;
    $moderation = $task->get('moderation_state')->value;
    foreach ([$status, $moderation] as $value) {
      if ($value !== NULL && $value !== '' && !isset($map[$value])) {
        throw new UpdateException(sprintf('Task %s (%s) has unknown workflow value "%s". Resolve it explicitly and rerun.', $task->id(), $task->language()->getId(), $value));
      }
    }
    // Board states and intentional drafts are authoritative. Legacy Published
    // did not encode a board column: recover it from the recognized status.
    $canonical = $moderation && $moderation !== 'published' ? $map[$moderation] : ($map[$status ?? ''] ?? $map[$moderation ?? ''] ?? NULL);
    if (!$canonical || !isset($states[$canonical])) {
      throw new UpdateException(sprintf('Task %s has no recoverable workflow state.', $task->id()));
    }
    return $canonical;
  };

  if (!isset($sandbox['ids'])) {
    $sandbox['ids'] = array_values($storage->getQuery()->accessCheck(FALSE)->condition('type', 'task')->sort('nid')->execute());
    foreach ($sandbox['ids'] as $id) {
      $task = $storage->load($id);
      // Do not promote or overwrite a pending revision during data repair.
      if ((int) $storage->getLatestRevisionId($id) !== (int) $task->getRevisionId()) {
        throw new UpdateException("Task $id has a pending revision. Reconcile its revision history explicitly before rerunning.");
      }
      foreach ($task->getTranslationLanguages() as $langcode => $language) {
        $resolve($task->getTranslation($langcode));
      }
    }
    // The workflow retains Draft and Published. Allow these canonical values
    // without changing the workflow or forcing such Tasks into a board column.
    $allowed = $field->getSetting('allowed_values');
    foreach ($states as $id => $state) {
      $allowed[$id] ??= (string) $state->label();
    }
    if ($allowed !== $field->getSetting('allowed_values')) {
      $field->setSetting('allowed_values', $allowed)->save();
    }
    $sandbox['processed'] = 0;
    $sandbox['updated'] = 0;
  }

  foreach (array_slice($sandbox['ids'], $sandbox['processed'], 25) as $id) {
    $task = $storage->load($id);
    $changed = FALSE;
    $original = [];
    foreach ($task->getTranslationLanguages() as $langcode => $language) {
      $translation = $task->getTranslation($langcode);
      $canonical = $resolve($translation);
      $published = $states[$canonical]->isPublishedState();
      $original[$langcode] = [
        $translation->get('field_status')->value,
        $translation->get('moderation_state')->value,
        $translation->isPublished(),
      ];
      if ($translation->get('field_status')->value !== $canonical || $translation->get('moderation_state')->value !== $canonical || $translation->isPublished() !== $published) {
        $translation->set('moderation_state', $canonical);
        $translation->set('field_status', $canonical);
        $translation->set('status', $published);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $task->setNewRevision(TRUE);
      $task->setRevisionLogMessage('Task workflow reconciliation; previous [status, moderation, published]: ' . json_encode($original));
      $task->save();
      $sandbox['updated']++;
    }
    $sandbox['processed']++;
  }
  $sandbox['#finished'] = $sandbox['ids'] ? $sandbox['processed'] / count($sandbox['ids']) : 1;
  return sprintf('Processed %d Tasks; reconciled %d Tasks. Original revisions retained.', $sandbox['processed'], $sandbox['updated']);
}
