<?php

namespace Drupal\drupaljira_timelog\Service;

/**
 * Formats durations represented as decimal hours.
 */
class DurationFormatter {

  /**
   * Formats a number of hours.
   *
   * @param float $hours
   *   The number of hours.
   *
   * @return string
   *   The formatted duration.
   */
  public function format(float $hours): string {
    $formatted = rtrim(
      rtrim(number_format($hours, 2, '.', ''), '0'),
      '.'
    );

    return $formatted . ' ч.';
  }

  /**
   * Formats a time summary.
   *
   * @param float $estimate
   *   The estimated hours.
   * @param float $logged
   *   The logged hours.
   * @param float $remaining
   *   The remaining hours.
   *
   * @return string
   *   The formatted time summary.
   */
  public function formatSummary(
    float $estimate,
    float $logged,
    float $remaining,
  ): string {
    return sprintf(
      '%s (%s written off, %s remaining)',
      $this->format($estimate),
      $this->format($logged),
      $this->format($remaining)
    );
  }

}
