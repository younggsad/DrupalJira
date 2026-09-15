<?php

namespace Drupal\drupaljira_timelog\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a ReportGenerator plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ReportGenerator extends Plugin {

  /**
   * Constructs a ReportGenerator attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $label
   *   The human-readable plugin label.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
  ) {
  }

}
