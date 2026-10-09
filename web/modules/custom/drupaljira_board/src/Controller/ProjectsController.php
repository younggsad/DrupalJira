<?php

namespace Drupal\drupaljira_board\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Composes the application landing page from accessible existing entities.
 */
final class ProjectsController extends ControllerBase {

  /**
   * Constructs the projects controller.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Displays accessible projects and the current user's assigned tasks.
   */
  public function page(): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user', 'user.permissions', 'url.query_args.pagers']);
    $cache->addCacheTags(['node_list', 'node_list:project', 'node_list:task']);
    $storage = $this->entities->getStorage('node');
    $projects = [];
    $tasks = [];

    if ($this->account->hasPermission('access content')) {
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'project')
        ->sort('changed', 'DESC')
        ->sort('nid', 'DESC')
        ->pager(12)
        ->execute();
      foreach ($storage->loadMultiple($ids) as $project) {
        $access = $project->access('view', $this->account, TRUE);
        $cache->addCacheableDependency($access);
        $cache->addCacheableDependency($project);
        if (!$access->isAllowed()) {
          continue;
        }
        $type = $project->get('field_project_type');
        $type_access = $type->access('view', $this->account, TRUE);
        $cache->addCacheableDependency($type_access);
        $projects[] = [
          'title' => $project->label(),
          'project_type' => $type_access->isAllowed() ? $type->view(['label' => 'hidden']) : [],
          'project_link' => $this->link($this->t('Open project'), $project->toUrl(), $cache),
          'board_link' => $this->link($this->t('Task Board'), Url::fromRoute(
            'view.task_board.page_1', ['arg_0' => $project->id()],
          ), $cache),
        ];
      }

      if ($this->account->isAuthenticated()) {
        $ids = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('type', 'task')
          ->condition('field_assignee', $this->account->id())
          ->sort('changed', 'DESC')
          ->sort('nid', 'DESC')
          ->range(0, 8)
          ->execute();
        foreach ($storage->loadMultiple($ids) as $task) {
          $access = $task->access('view', $this->account, TRUE);
          $cache->addCacheableDependency($access);
          $cache->addCacheableDependency($task);
          if ($access->isAllowed()) {
            $tasks[] = $this->link($task->label(), $task->toUrl(), $cache);
          }
        }
      }
    }

    $build = [
      '#theme' => 'drupaljira_projects',
      '#projects' => $projects,
      '#tasks' => $tasks,
      '#authenticated' => $this->account->isAuthenticated(),
      '#create_project' => $this->link($this->t('Create project'), Url::fromRoute(
        'node.add', ['node_type' => 'project'],
      ), $cache, ['button', 'button--primary']),
      '#sign_in' => $this->link($this->t('Sign in'), Url::fromRoute(
        'user.login', [], ['query' => ['destination' => '/projects']],
      ), $cache, ['button', 'button--primary']),
      '#pager' => ['#type' => 'pager'],
      '#attached' => ['library' => ['drupaljira_theme/projects']],
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Builds a permitted navigation link and retains its access metadata.
   */
  private function link(mixed $title, Url $url, CacheableMetadata $cache, array $classes = []): array {
    $access = $url->access($this->account, TRUE);
    $cache->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return [];
    }
    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => $url,
      '#attributes' => ['class' => $classes],
    ];
  }

}
