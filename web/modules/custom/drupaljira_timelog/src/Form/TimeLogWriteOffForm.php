<?php

declare(strict_types=1);

namespace Drupal\drupaljira_timelog\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drupaljira_timelog\Event\TimeLogCreatedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for logging time against a task.
 */
final class TimeLogWriteOffForm extends FormBase {

  /**
   * Constructs a TimeLogWriteOffForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CurrentRouteMatch $currentRouteMatch,
    private readonly AccountProxyInterface $currentUser,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupaljira_timelog_write_off_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Hours'),
      '#required' => TRUE,
      '#step' => 0.01,
    ];

    $form['log_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Log date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
    ];

    $form['over_estimate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("I'm writing off more hours"),
    ];

    $form['over_estimate_reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reason for exceeding'),
      '#states' => [
        'visible' => [
          ':input[name="over_estimate"]' => [
            'checked' => TRUE,
          ],
        ],
        'required' => [
          ':input[name="over_estimate"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Log time'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $hours = $form_state->getValue('hours');
    $log_date = $form_state->getValue('log_date');
    $over_estimate = $form_state->getValue('over_estimate');
    $reason = trim((string) $form_state->getValue('over_estimate_reason'));

    if (!is_numeric($hours) || (float) $hours <= 0) {
      $form_state->setErrorByName(
        'hours',
        $this->t('Hours must be greater than zero.')
      );
    }

    $today = date('Y-m-d');

    if ($log_date > $today) {
      $form_state->setErrorByName(
        'log_date',
        $this->t('Log date cannot be later than today.')
      );
    }

    if ($over_estimate && $reason === '') {
      $form_state->setErrorByName(
        'over_estimate_reason',
        $this->t('Reason for exceeding is required when writing off more hours.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $task = $this->currentRouteMatch->getParameter('task');
    $values = $form_state->getValues();

    /** @var \Drupal\drupaljira_timelog\TimeLogInterface $time_log */
    $time_log = $this->entityTypeManager
      ->getStorage('time_log')
      ->create([
        'task' => [
          'target_id' => $task->id(),
        ],
        'uid' => $this->currentUser->id(),
        'hours' => $values['hours'],
        'log_date' => $values['log_date'],
        'notes' => $values['notes'],
        'over_estimate_reason' => $values['over_estimate']
          ? $values['over_estimate_reason']
          : '',
      ]);

    $time_log->save();

    $this->eventDispatcher->dispatch(
      new TimeLogCreatedEvent($time_log)
    );

    $this->messenger()->addStatus(
      $this->t('Time has been logged successfully.')
    );

    $form_state->setRedirect(
      'entity.node.canonical',
      ['node' => $task->id()]
    );
  }

}
