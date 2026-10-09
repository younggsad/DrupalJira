<?php

/**
 * @file
 * Run with ddev drush php:script ../tests/integration/timelog-statistics.php.
 */

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\RenderContext;
use Drupal\drupaljira_timelog\Entity\TimeLog;

/**
 * Assert a TimeLog regression check.
 */
function timelog_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$manager = \Drupal::entityTypeManager();
$nodes = $manager->getStorage('node');
$logs = $manager->getStorage('time_log');
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($manager->getStorage('user')->load(1));
$fixtures = [];
$log = NULL;
$cache = \Drupal::cache();
$keys = [];
try {
  foreach (['A', 'B'] as $name) {
    $project = $nodes->create(['type' => 'project', 'title' => 'Statistics regression ' . $name, 'status' => 1]);
    $project->save();
    $fixtures[] = $project;
    $task = $nodes->create([
      'type' => 'task',
      'title' => 'Statistics regression ' . $name,
      'field_project' => $project->id(),
      'field_estimate' => 4,
      'moderation_state' => 'backlog',
    ]);
    $task->save();
    $fixtures[] = $task;
    $projects[] = $project;
    $tasks[] = $task;
  }
  $service = \Drupal::service('drupaljira.task_stat');
  $prime = static function () use ($cache, &$keys, $projects, $tasks): void {
    foreach (array_merge($projects, $tasks) as $entity) {
      $tags = $entity->getCacheTags();
      if ($entity->bundle() === 'project') {
        $tags[] = 'drupaljira_project_stats:' . $entity->id();
      }
      foreach ($tags as $tag) {
        $key = 'timelog-regression:' . $tag;
        $keys[$key] = $key;
        $cache->set($key, TRUE, Cache::PERMANENT, [$tag]);
      }
    }
  };
  $invalidated = static function (int $index) use ($cache, $projects, $tasks): void {
    $tags = [
      'node:' . $tasks[$index]->id(),
      'node:' . $projects[$index]->id(),
      'drupaljira_project_stats:' . $projects[$index]->id(),
    ];
    foreach ($tags as $tag) {
      timelog_check(!$cache->get('timelog-regression:' . $tag), 'Stale cache: ' . $tag);
    }
  };
  $prime();
  $log = $logs->create([
    'task' => $tasks[0]->id(),
    'uid' => 1,
    'hours' => 2,
    'log_date' => '2026-10-09',
  ]);

  if (!$log instanceof TimeLog) {
    throw new RuntimeException('Failed to create a TimeLog entity.');
  }

  $log->save();
  $invalidated(0);
  timelog_check($service->getProjectStats($projects[0])['total_logged'] === 2.0, 'Creation total mismatch.');

  $route = \Drupal::service('router.route_provider')->getRouteByName('entity.time_log.canonical');
  timelog_check($route->getDefault('_entity_form') === 'time_log.default', 'Canonical route mismatch.');
  $form = \Drupal::service('entity.form_builder')->getForm($log, 'default');
  $renderer = \Drupal::service('renderer');
  $html = $renderer->executeInRenderContext(new RenderContext(), static function () use ($renderer, &$form) {
    return (string) $renderer->render($form);
  });
  timelog_check(str_contains($html, '<form') && str_contains($html, 'hours'), 'Canonical form did not render.');

  $prime();
  $log->set('hours', 5)->save();
  $invalidated(0);
  $stats = $service->getProjectStats($projects[0]);
  timelog_check($stats['total_logged'] === 5.0 && $stats['remaining_hours'] === -1.0 && $stats['over_estimate_count'] === 1, 'Updated statistics mismatch.');
  $prime();
  $log->set('task', $tasks[1]->id())->save();
  $invalidated(0);
  $invalidated(1);
  timelog_check($service->getProjectStats($projects[0])['total_logged'] === 0.0, 'Old project total stale.');
  timelog_check($service->getProjectStats($projects[1])['total_logged'] === 5.0, 'New project total mismatch.');

  // Multiple tasks must share one TimeLog query, including zero-log tasks.
  $task = $nodes->create([
    'type' => 'task',
    'title' => 'Statistics regression empty task',
    'field_project' => $projects[1]->id(),
    'field_estimate' => 3,
    'moderation_state' => 'backlog',
  ]);
  $task->save();
  $fixtures[] = $task;
  $nodes->resetCache();
  $logs->resetCache();
  Database::startLog('timelog_regression');
  $stats = $service->getProjectStats($projects[1], new CacheableMetadata());
  $queries = Database::getLog('timelog_regression');
  $log_queries = array_filter($queries, static fn(array $query): bool => preg_match('/FROM\s+"time_log"/', $query['query']) === 1);
  timelog_check(count($log_queries) === 2, 'Expected one log ID query and one batch load.');
  timelog_check($stats['task_count'] === 2 && $stats['total_estimate'] === 7.0 && $stats['total_logged'] === 5.0, 'Batch statistics mismatch.');
  $prime();
  $extra_key = 'timelog-regression:node:' . $task->id();
  $keys[$extra_key] = $extra_key;
  $cache->set($extra_key, TRUE, Cache::PERMANENT, $task->getCacheTags());
  $log->set('task', $task->id())->save();
  $invalidated(1);
  timelog_check(!$cache->get($extra_key), 'Same-project target task cache stale.');
  timelog_check($service->getLoggedHours($tasks[1]) === 0.0 && $service->getLoggedHours($task) === 5.0, 'Same-project reassignment mismatch.');
  $prime();
  $log->delete();
  $log = NULL;
  timelog_check(!$cache->get('timelog-regression:drupaljira_project_stats:' . $projects[1]->id()), 'Deletion project cache stale.');
  timelog_check($service->getProjectStats($projects[1])['total_logged'] === 0.0, 'Deletion total stale.');
  echo "PASS: canonical form, create/update/reassignment/delete invalidation, statistics and batched queries.\n";
}
finally {
  $log?->delete();
  foreach (array_reverse($fixtures) as $entity) {
    $entity->delete();
  }
  foreach ($keys as $key) {
    $cache->delete($key);
  }
  $switcher->switchBack();
}
