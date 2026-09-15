<?php

namespace Drupal\drupaljira_timelog\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an Hours + Minutes widget for decimal fields.
 */
#[FieldWidget(
  id: 'hours_minutes',
  label: new TranslatableMarkup('Hours + Minutes'),
  field_types: ['decimal'],
)]
final class HoursMinutesWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $value = $items[$delta]->value ?? NULL;

    $hours = 0;
    $minutes = 0;

    if ($value !== NULL && $value !== '') {
      $total_minutes = (int) round((float) $value * 60);
      $hours = intdiv($total_minutes, 60);
      $minutes = $total_minutes % 60;
    }

    $element['hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Hours'),
      '#default_value' => $hours,
      '#min' => 0,
      '#step' => 1,
    ];

    $element['minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Minutes'),
      '#default_value' => $minutes,
      '#min' => 0,
      '#max' => 59,
      '#step' => 1,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state,
  ): array {
    foreach ($values as $delta => $value) {
      $hours = (float) ($value['hours'] ?? 0);
      $minutes = (float) ($value['minutes'] ?? 0);

      if ($hours === 0.0 && $minutes === 0.0) {
        $values[$delta] = [
          'value' => 0,
        ];
        continue;
      }

      $total_minutes = ($hours * 60) + $minutes;
      $decimal_hours = round($total_minutes / 60, 2);

      $values[$delta] = [
        'value' => $decimal_hours,
      ];
    }

    return $values;
  }

}
