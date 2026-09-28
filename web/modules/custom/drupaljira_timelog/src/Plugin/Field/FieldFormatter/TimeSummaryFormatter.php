<?php

namespace Drupal\drupaljira_timelog\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupaljira_timelog\Service\DurationFormatter;
use Drupal\drupaljira_timelog\Service\TaskStatService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Time Summary formatter for decimal fields.
 */
#[FieldFormatter(
  id: 'time_summary',
  label: new TranslatableMarkup('Time Summary'),
  field_types: ['decimal'],
)]
final class TimeSummaryFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a TimeSummaryFormatter instance.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected TaskStatService $taskStatService,
    protected DurationFormatter $durationFormatter,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
    );
  }

  /**
   * Creates the formatter using dependency injection.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('drupaljira.task_stat'),
      $container->get('drupaljira_timelog.duration_formatter'),
    );
  }

  /**
   * Builds the render array for the field values.
   */
  public function viewElements(
    FieldItemListInterface $items,
    $langcode,
  ): array {
    $entity = $items->getEntity();

    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'task') {
      return [];
    }

    $task = $entity;
    $elements = [];

    foreach ($items as $delta => $item) {
      $estimate = (float) ($item->getValue()['value'] ?? 0);
      $remaining = $this->taskStatService->getRemainingEstimate($task);
      $logged = $estimate - $remaining;

      $elements[$delta] = [
        '#plain_text' => $this->durationFormatter->formatSummary(
          $estimate,
          $logged,
          $remaining,
        ),
      ];
    }

    return $elements;
  }

}
