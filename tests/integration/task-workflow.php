<?php

/**
 * @file
 * Run with drush php:script on a disposable workflow_review database only.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Utility\UpdateException;
use Drupal\content_moderation\ContentModerationState;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\process\StaticMap;
use Symfony\Component\Yaml\Yaml;

if (Database::getConnection()->getConnectionOptions()['database'] !== 'workflow_review') {
  throw new RuntimeException('This test requires the disposable workflow_review database.');
}
require_once DRUPAL_ROOT . '/modules/custom/drupaljira_timelog/drupaljira_timelog.post_update.php';

/**
 * Assert a workflow invariant.
 */
function workflow_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'task')->execute();
$before = [];
foreach ($storage->loadMultiple($ids) as $task) {
  $before[$task->id()] = $task->getRevisionId();
}
$sandbox = [];
do {
  drupaljira_timelog_post_update_reconcile_task_workflow($sandbox);
} while ($sandbox['#finished'] < 1);
workflow_check($sandbox['updated'] === 0, 'Second run must not change Tasks.');
$storage->resetCache();
foreach ($storage->loadMultiple($ids) as $task) {
  workflow_check($task->getRevisionId() === $before[$task->id()], 'Idempotency must retain revision IDs.');
  workflow_check($task->get('field_status')->value === $task->get('moderation_state')->value, 'Status mismatch.');
  $state = \Drupal::entityTypeManager()->getStorage('workflow')->load('task_workflow')->getTypePlugin()->getState($task->get('moderation_state')->value);
  if (!$state instanceof ContentModerationState) {
    throw new RuntimeException('Task workflow must provide a Content Moderation state.');
  }
  workflow_check($task->isPublished() === $state->isPublishedState(), 'Publication mismatch.');
}
echo "PASS: canonical states, publication and revision idempotency.\n";

// Simulate pre-existing corrupt data only in this disposable database. The
// Repair uses entity APIs; this fixture bypasses the synchronization hook.
$id = reset($ids);
$original = $storage->load($id)->get('field_status')->value;
$connection = Database::getConnection();
$connection->update('node__field_status')->fields(['field_status_value' => 'unknown_legacy'])->condition('entity_id', $id)->execute();
$storage->resetCache();
try {
  $sandbox = [];
  drupaljira_timelog_post_update_reconcile_task_workflow($sandbox);
  throw new RuntimeException('Unknown status was accepted.');
}
catch (UpdateException $exception) {
  workflow_check(str_contains($exception->getMessage(), 'unknown_legacy'), 'Unknown status must be reported.');
}
finally {
  $connection->update('node__field_status')->fields(['field_status_value' => $original])->condition('entity_id', $id)->execute();
  $storage->resetCache();
}
echo "PASS: unknown status stops preflight without creating revisions.\n";

// Cover legacy aliases, publication repair, and retained source revisions.
foreach (['todo' => 'backlog', 'working' => 'in_progress', 'qa' => 'review', 'completed' => 'done'] as $legacy => $canonical) {
  $task = $storage->create(['type' => 'task', 'title' => 'Workflow repair fixture', 'moderation_state' => 'published']);
  $task->save();
  $revision = $task->getRevisionId();
  $connection->update('node__field_status')->fields(['field_status_value' => $legacy])->condition('entity_id', $task->id())->execute();
  $connection->update('node_field_data')->fields(['status' => 0])->condition('nid', $task->id())->execute();
  $storage->resetCache();
  $sandbox = [];
  do {
    drupaljira_timelog_post_update_reconcile_task_workflow($sandbox);
  } while ($sandbox['#finished'] < 1);
  $storage->resetCache();
  $repaired = $storage->load($task->id());
  workflow_check($repaired->get('moderation_state')->value === $canonical, 'Legacy reconciliation mismatch.');
  workflow_check($repaired->get('field_status')->value === $canonical && $repaired->isPublished(), 'Legacy publication/status mismatch.');
  workflow_check($storage->loadRevision($revision) !== NULL, 'Original revision must remain.');
  $repaired->delete();
}
echo "PASS: four legacy aliases, publication repair and original revisions.\n";

$task = $storage->create(['type' => 'task', 'title' => 'Pending revision fixture', 'moderation_state' => 'backlog']);
$task->save();
$task->setNewRevision(TRUE);
$task->set('moderation_state', 'draft');
$task->save();
$storage->resetCache();
try {
  $sandbox = [];
  drupaljira_timelog_post_update_reconcile_task_workflow($sandbox);
  throw new RuntimeException('Pending revision was accepted.');
}
catch (UpdateException $exception) {
  workflow_check(str_contains($exception->getMessage(), 'pending revision'), 'Pending revision must be reported.');
}
finally {
  $storage->load($task->id())->delete();
}
echo "PASS: pending revisions stop preflight rather than being promoted.\n";

$definition = Yaml::parseFile(DRUPAL_ROOT . '/modules/custom/drupaljira_timelog/migrations/drupaljira_backlog.yml');
// A fresh ID leaves the real migration and its existing maps untouched.
$definition['id'] = 'workflow_review_backlog';
$migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
$executable = new MigrateExecutable($migration, new MigrateMessage());
foreach (['todo' => 'backlog', 'working' => 'in_progress', 'qa' => 'review', 'completed' => 'done'] as $legacy => $canonical) {
  $row = new Row(['id' => 999, 'status' => $legacy], ['id' => ['type' => 'integer']]);
  $executable->processRow($row);
  workflow_check($row->getDestinationProperty('moderation_state') === $canonical, 'Process must establish moderation before save.');
  workflow_check($row->getDestinationProperty('field_status') === $canonical, 'Process must establish status before save.');
}
$status_configuration = $definition['process']['moderation_state'];
$status_map = \Drupal::service('plugin.manager.migrate.process')->createInstance($status_configuration['plugin'], $status_configuration, $migration);
if (!$status_map instanceof StaticMap) {
  throw new RuntimeException('Migration moderation state must use static_map.');
}
try {
  $row = new Row(['id' => 999, 'status' => 'unknown_legacy'], ['id' => ['type' => 'integer']]);
  $status_map->transform($row->getSourceProperty('status'), $executable, $row, 'moderation_state');
  throw new RuntimeException('Migration accepted an unknown status.');
}
catch (MigrateSkipRowException) {
  echo "PASS: migration rejects unknown statuses and sets both fields before save.\n";
}
workflow_check($executable->import() === MigrationInterface::RESULT_COMPLETED, 'Import failed.');
$expected = ['backlog', 'in_progress', 'review', 'done', 'backlog', 'in_progress'];
foreach ($expected as $index => $state) {
  $destination = $migration->getIdMap()->lookupDestinationIds(['id' => $index + 1]);
  workflow_check(!empty($destination[0][0]), 'Missing migrated row.');
  $task = $storage->load($destination[0][0]);
  workflow_check($task->get('moderation_state')->value === $state, 'Migration moderation mismatch.');
  workflow_check($task->get('field_status')->value === $state, 'Migration status mismatch.');
  workflow_check($task->isPublished(), 'Imported board Tasks must be published.');
}
echo "PASS: all six CSV rows imported with matching workflow/status and publication.\n";
