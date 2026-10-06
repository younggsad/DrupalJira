<?php

/**
 * @file
 * Local-only E2E provisioning, invoked by Drush with a private JSON input file.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\content_moderation\Plugin\Validation\Constraint\ModerationStateConstraint;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\Role;

/**
 * Verifies the supported site and the existing permission/data contract.
 */
function e2e_target(array $input): void {
  $ddev_url = getenv('DDEV_PRIMARY_URL');
  if (!$ddev_url || ($input['baseURL'] ?? '') !== rtrim($ddev_url, '/')
    || getenv('DDEV_PROJECT') !== 'DrupalJira'
    || \Drupal::config('system.site')->get('uuid') !== '8c3b594d-e746-465e-90a3-4f26b2aa70cd') {
    throw new RuntimeException('Fixture target does not match local DrupalJira.');
  }
}

/**
 * Checks the active data model without altering configuration.
 */
function e2e_preflight(array $input): void {
  e2e_target($input);
  $fields = \Drupal::service('entity_field.manager');
  foreach ([
    'project' => ['field_project_type', 'field_body'],
    'task' => [
      'field_project', 'field_assignee', 'field_estimate', 'field_body',
      'field_status', 'field_attachments', 'moderation_state',
    ],
  ] as $bundle => $required) {
    $definitions = $fields->getFieldDefinitions('node', $bundle);
    foreach ($required as $field) {
      if (!isset($definitions[$field])) {
        throw new RuntimeException("Missing $bundle.$field");
      }
    }
  }
  $workflow = \Drupal::entityTypeManager()->getStorage('workflow')->load('task_workflow');
  $type_plugin = $workflow?->getTypePlugin();
  if (!$type_plugin instanceof ContentModerationInterface
    || !in_array('task', $type_plugin->getBundlesForEntityType('node'), TRUE)) {
    throw new RuntimeException('Task moderation workflow is missing.');
  }
  foreach (['backlog', 'in_progress', 'review', 'done'] as $state) {
    if (!$workflow->getTypePlugin()->hasState($state)) {
      throw new RuntimeException("Missing workflow state $state");
    }
  }
  $authenticated = Role::load('authenticated');
  $manager = Role::load('project_manager');
  if (!$authenticated || !$manager || $authenticated->isAdmin() || $manager->isAdmin()
    || !$manager->hasPermission('use task_workflow transition approve')
    || $authenticated->hasPermission('use task_workflow transition approve')
    || !$authenticated->hasPermission('edit any task content')
    || !$authenticated->hasPermission('administer time_log')) {
    throw new RuntimeException('Existing persona permissions differ from the E2E contract.');
  }
  foreach (['task', 'uid', 'hours', 'log_date', 'notes', 'over_estimate_reason'] as $field) {
    if (!isset($fields->getBaseFieldDefinitions('time_log')[$field])) {
      throw new RuntimeException("Missing TimeLog field $field");
    }
  }
  foreach (['image' => 'field_media_image', 'document' => 'field_media_file'] as $bundle => $field) {
    if (!isset($fields->getFieldDefinitions('media', $bundle)[$field])) {
      throw new RuntimeException("Missing Media $bundle.$field");
    }
  }
}

/**
 * Creates a deterministic UUID within an isolated namespace.
 */
function e2e_uuid(string $namespace, string $key): string {
  $hash = hash('sha256', 'drupaljira-e2e:' . $namespace . ':' . $key);
  return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4'
    . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-'
    . substr($hash, 20, 12);
}

/**
 * Records ownership before saving, then validates and saves through Entity API.
 */
function e2e_create(string $type, string $key, array $values, array &$ledger): ContentEntityInterface {
  $uuid = e2e_uuid($ledger['namespace'], $key);
  $storage = \Drupal::entityTypeManager()->getStorage($type);
  $existing = $storage->loadByProperties(['uuid' => $uuid]);
  if ($existing) {
    throw new RuntimeException('Namespace already seeded; reset it before seeding again.');
  }
  $ledger['entities'][$type][] = $uuid;
  \Drupal::state()->set('drupaljira_e2e.' . $ledger['namespace'], $ledger);
  $entity = $storage->create(['uuid' => $uuid] + $values);
  if (!$entity instanceof ContentEntityInterface) {
    throw new RuntimeException('Fixtures can create content entities only.');
  }
  if ($type === 'node' && ($values['type'] ?? '') === 'task') {
    $workflow = \Drupal::entityTypeManager()->getStorage('workflow')->load('task_workflow');
    if (!$workflow->getTypePlugin()->hasState($values['moderation_state'] ?? '')) {
      throw new RuntimeException('Unknown fixture moderation state.');
    }
  }
  $violations = $entity->validate();
  foreach ($violations as $violation) {
    // Fixture snapshots initialize states directly, not through a transition.
    // Validate every other constraint; state existence is checked separately.
    if ($type === 'node' && ($values['type'] ?? '') === 'task'
      && $violation->getConstraint() instanceof ModerationStateConstraint) {
      continue;
    }
    throw new RuntimeException("Fixture $type/$key failed validation at " . $violation->getPropertyPath());
  }
  $entity->save();
  return $entity;
}

/**
 * Deletes only ledger-owned entities and dependents on owned tasks.
 */
function e2e_cleanup(string $namespace): array {
  $state = \Drupal::state();
  $ledger = $state->get('drupaljira_e2e.' . $namespace);
  if (!$ledger) {
    return ['cleaned' => TRUE, 'namespace' => $namespace];
  }
  if (($ledger['namespace'] ?? NULL) !== $namespace) {
    throw new RuntimeException('Ownership ledger mismatch.');
  }
  $manager = \Drupal::entityTypeManager();
  $nodes = [];
  foreach ($ledger['entities']['node'] ?? [] as $uuid) {
    $nodes += $manager->getStorage('node')->loadByProperties(['uuid' => $uuid]);
  }
  $tasks = array_filter($nodes, fn($node) => $node->bundle() === 'task');
  if ($tasks) {
    $storage = $manager->getStorage('time_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('task', array_keys($tasks), 'IN')->execute();
    $storage->delete($storage->loadMultiple($ids));
    $manager->getStorage('node')->delete($tasks);
  }
  foreach (['time_log', 'node', 'media', 'file', 'user'] as $type) {
    $storage = $manager->getStorage($type);
    foreach ($ledger['entities'][$type] ?? [] as $uuid) {
      $storage->delete($storage->loadByProperties(['uuid' => $uuid]));
    }
  }
  foreach ($ledger['files'] ?? [] as $uri) {
    if (!str_starts_with($uri, "public://e2e/$namespace/")) {
      throw new RuntimeException('Refusing to remove an unowned file.');
    }
    if (file_exists($uri)) {
      \Drupal::service('file_system')->delete($uri);
    }
  }
  $directory = "public://e2e/$namespace";
  if (is_dir($directory)) {
    // Remove only empty directories; never recursively delete unknown files.
    @rmdir($directory);
  }
  $state->delete('drupaljira_e2e.' . $namespace);
  return ['cleaned' => TRUE, 'namespace' => $namespace];
}

/**
 * Creates two existing-role personas without changing role configuration.
 */
function e2e_accounts(array $input, array &$ledger): array {
  $accounts = [];
  foreach (['manager' => ['project_manager'], 'regular' => []] as $persona => $roles) {
    $name = 'e2e-' . $persona . '-' . substr(hash('sha256', $ledger['namespace']), 0, 32);
    $password = $input[$persona === 'manager' ? 'managerPassword' : 'userPassword'] ?? '';
    if (strlen($password) < 16) {
      throw new RuntimeException('Fixture passwords must be at least 16 characters.');
    }
    $user = e2e_create('user', $persona, [
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'pass' => $password,
      'status' => 1,
      'roles' => $roles,
    ], $ledger);
    $accounts[$persona] = ['id' => (int) $user->id(), 'name' => $name];
  }
  $ledger['accounts'] = $accounts;
  \Drupal::state()->set('drupaljira_e2e.' . $ledger['namespace'], $ledger);
  return $accounts;
}

/**
 * Creates Projects, all board states, TimeLogs, and optional Media attachments.
 */
function e2e_scenario(array $input, array &$ledger): array {
  $users = $input['users'] ?? $ledger['accounts'] ?? NULL;
  if (!$users) {
    throw new RuntimeException('Scenario requires fixture accounts.');
  }
  foreach ($users as $user) {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($user['id']);
    if (!$account || $account->getAccountName() !== $user['name']
      || !str_starts_with($user['name'], 'e2e-')) {
      throw new RuntimeException('Scenario references an invalid fixture account.');
    }
  }
  $result = ['namespace' => $ledger['namespace'], 'projects' => [], 'tasks' => []];
  foreach (['kanban', 'scrum'] as $type) {
    $project = e2e_create('node', $type, [
      'type' => 'project',
      'title' => 'E2E ' . $type . ' ' . $ledger['namespace'],
      'uid' => $users['manager']['id'],
      'status' => 1,
      'field_project_type' => $type,
      'field_body' => ['value' => 'Deterministic E2E project.', 'format' => 'plain_text'],
    ], $ledger);
    $result['projects'][$type] = (int) $project->id();
  }
  foreach (['backlog', 'in_progress', 'review', 'done'] as $state) {
    $title = 'E2E ' . $state . ' ' . $ledger['namespace'];
    $task = e2e_create('node', $state, [
      'type' => 'task',
      'title' => $title,
      'uid' => $users['manager']['id'],
      'status' => 1,
      'field_project' => $result['projects']['kanban'],
      'field_assignee' => $users['regular']['id'],
      'field_estimate' => '8.00',
      'field_body' => ['value' => 'Deterministic E2E task.', 'format' => 'plain_text'],
      'moderation_state' => $state,
    ], $ledger);
    $result['tasks'][$state] = ['id' => (int) $task->id(), 'title' => $title];
    if ($task->get('field_status')->value !== $state) {
      throw new RuntimeException('Board status synchronization failed.');
    }
  }
  e2e_create('time_log', 'log', [
    'task' => $result['tasks']['backlog']['id'],
    'uid' => $users['regular']['id'],
    'hours' => '2.00',
    'log_date' => '2020-01-02',
    'notes' => 'Deterministic fixture time.',
    'over_estimate_reason' => '',
    'created' => 1577923200,
  ], $ledger);
  if (!empty($input['media'])) {
    $file_system = \Drupal::service('file_system');
    $directory = 'public://e2e/' . $ledger['namespace'];
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $attachments = [];
    foreach (['image' => 'fixture.png', 'document' => 'fixture.txt'] as $bundle => $filename) {
      $uri = "$directory/$filename";
      $ledger['files'][] = $uri;
      \Drupal::state()->set('drupaljira_e2e.' . $ledger['namespace'], $ledger);
      $bytes = file_get_contents(DRUPAL_ROOT . '/../tests/e2e/assets/' . $filename);
      $file_system->saveData($bytes, $uri, FileExists::Error);
      $file = e2e_create('file', $filename, [
        'uid' => $users['manager']['id'],
        'filename' => $filename,
        'uri' => $uri,
        'filemime' => $bundle === 'image' ? 'image/png' : 'text/plain',
        'status' => 1,
      ], $ledger);
      $field = $bundle === 'image' ? 'field_media_image' : 'field_media_file';
      $file_field = ['target_id' => $file->id()];
      if ($bundle === 'image') {
        $file_field['alt'] = 'E2E fixture image';
      }
      $media = e2e_create('media', $bundle, [
        'bundle' => $bundle,
        'uid' => $users['manager']['id'],
        'name' => "E2E $bundle " . $ledger['namespace'],
        'status' => 1,
        $field => $file_field,
      ], $ledger);
      $attachments[] = ['target_id' => $media->id()];
    }
    $task = \Drupal::entityTypeManager()->getStorage('node')->load($result['tasks']['backlog']['id']);
    $task->set('field_attachments', $attachments)->save();
    $result['media'] = $attachments;
  }
  return $result;
}

$input_path = $extra[0] ?? '';
if (!str_starts_with($input_path, '/var/www/html/.playwright/inputs/')) {
  throw new RuntimeException('Expected an ignored E2E input file.');
}
$input = json_decode(file_get_contents($input_path), TRUE, 512, JSON_THROW_ON_ERROR);
$namespace = $input['namespace'] ?? '';
if (!preg_match('/^[a-zA-Z0-9-]{1,100}$/', $namespace)) {
  throw new RuntimeException('Invalid fixture namespace.');
}
if (in_array($input['operation'] ?? '', ['cleanup', 'list'], TRUE)) {
  e2e_target($input);
}
else {
  e2e_preflight($input);
}
$ledger = \Drupal::state()->get('drupaljira_e2e.' . $namespace, [
  'namespace' => $namespace,
  'entities' => [],
  'files' => [],
]);
$result = match ($input['operation'] ?? '') {
  'accounts' => e2e_accounts($input, $ledger),
  'scenario' => e2e_scenario($input, $ledger),
  'cleanup' => e2e_cleanup($namespace),
  'preflight' => ['ready' => TRUE],
  'list' => array_values(array_map(
    fn($entry) => $entry['namespace'],
    array_filter(
      \Drupal::keyValue('state')->getAll(),
      fn($key) => str_starts_with($key, 'drupaljira_e2e.'),
      ARRAY_FILTER_USE_KEY,
    ),
  )),
  default => throw new RuntimeException('Unknown fixture operation.'),
};
print 'E2E_JSON:' . json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
