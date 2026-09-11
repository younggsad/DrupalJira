<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupaljira_timelog\Form\TimeLogForm;
use Drupal\drupaljira_timelog\TimeLogAccessControlHandler;
use Drupal\drupaljira_timelog\TimeLogInterface;
use Drupal\drupaljira_timelog\TimeLogListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the time log entity class.
 */
#[ContentEntityType(
  id: 'time_log',
  label: new TranslatableMarkup('Time Log'),
  label_collection: new TranslatableMarkup('Time Logs'),
  label_singular: new TranslatableMarkup('time log'),
  label_plural: new TranslatableMarkup('time logs'),
  entity_keys: [
    'id' => 'id',
    'owner' => 'uid',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => TimeLogListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => TimeLogAccessControlHandler::class,
    'form' => [
      'add' => TimeLogForm::class,
      'edit' => TimeLogForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/time-log',
    'add-form' => '/time-log/add',
    'canonical' => '/time-log/{time_log}',
    'edit-form' => '/time-log/{time_log}/edit',
    'delete-form' => '/time-log/{time_log}/delete',
    'delete-multiple-form' => '/admin/content/time-log/delete-multiple',
  ],
  admin_permission: 'administer time_log',
  base_table: 'time_log',
  label_count: [
    'singular' => '@count time log',
    'plural' => '@count time logs',
  ],
  field_ui_base_route: 'entity.time_log.settings',
)]
class TimeLog extends ContentEntityBase implements TimeLogInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // 1. Task (Entity Reference -> Node 'task') - REQUIRED.
    $fields['task'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Task'))
      ->setDescription(t('The task node associated with this time log.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'task' => 'task',
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // 2. User (uid) (Entity Reference -> User) - REQUIRED.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who logged time.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // 3. Hours (Decimal number) - Hours logged.
    $fields['hours'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Hours'))
      ->setDescription(t('The number of hours logged.'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // 4. Log Date (Date without time).
    $fields['log_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Log Date'))
      ->setDescription(t('Date when the time was spent.'))
      ->setSetting('datetime_type', 'date')
      ->setDefaultValue([
        'default_date_type' => 'now',
        'default_date' => 'now',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 3,
        'settings' => [
          'format_type' => 'html_date',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // 5. Notes (Text field, optional).
    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Optional notes about the work done.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // 6. Over estimate reason (Text field, optional).
    $fields['over_estimate_reason'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Over Estimate Reason'))
      ->setDescription(t('Reason for exceeding the estimate, if applicable.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Standard created and changed timestamps.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the time log was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the time log was last edited.'));

    return $fields;
  }

}
