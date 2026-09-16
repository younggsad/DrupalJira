<?php

namespace Drupal\drupaljira_timelog;

use Drupal\drupaljira_timelog\Attribute\ReportGenerator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages ReportGenerator plugins.
 */
class ReportGeneratorManager extends DefaultPluginManager {

  /**
   * Constructs a ReportGeneratorManager object.
   *
   * @param \Traversable $namespaces
   *   The root namespaces.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/ReportGenerator',
      $namespaces,
      $module_handler,
      ReportGeneratorInterface::class,
      ReportGenerator::class,
    );

    $this->setCacheBackend(
      $cache_backend,
      'report_generator_plugins',
    );
  }

}
