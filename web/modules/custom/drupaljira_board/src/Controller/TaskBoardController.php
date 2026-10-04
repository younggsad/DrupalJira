<?php

namespace Drupal\drupaljira_board\Controller;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides AJAX callbacks for the DrupalJira task board.
 */
final class TaskBoardController extends ControllerBase {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected ModerationInformationInterface $moderationInformation;

  /**
   * The moderation transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected StateTransitionValidationInterface $transitionValidation;

  /**
   * Constructs a TaskBoardController object.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $transition_validation
   *   The moderation transition validation service.
   */
  public function __construct(
    ModerationInformationInterface $moderation_information,
    StateTransitionValidationInterface $transition_validation,
  ) {
    $this->moderationInformation = $moderation_information;
    $this->transitionValidation = $transition_validation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation'),
    );
  }

  /**
   * Updates the status of a Task node.
   */
  public function updateStatus(
    NodeInterface $node,
    Request $request,
  ): JsonResponse {
    if ($node->bundle() !== 'task') {
      return new JsonResponse([
        'error' => 'The specified node is not a Task.',
      ], 400);
    }

    $data = json_decode($request->getContent(), TRUE);

    if (!is_array($data) || !isset($data['status'])) {
      return new JsonResponse([
        'error' => 'The status is required.',
      ], 400);
    }

    $status = $data['status'];

    $allowed_statuses = [
      'backlog',
      'in_progress',
      'review',
      'done',
    ];

    if (!in_array($status, $allowed_statuses, TRUE)) {
      return new JsonResponse([
        'error' => 'Invalid task status.',
      ], 400);
    }

    $workflow = $this->moderationInformation
      ->getWorkflowForEntity($node);

    if (!$workflow) {
      return new JsonResponse([
        'error' => 'No moderation workflow is configured for this Task.',
      ], 400);
    }

    $current_state = $node->get('moderation_state')->value;

    // The requested status is already the current moderation state.
    if ($current_state === $status) {
      $node->set('field_status', $status);
      $node->save();

      return new JsonResponse([
        'success' => TRUE,
        'task_id' => $node->id(),
        'status' => $status,
        'moderation_state' => $node->get('moderation_state')->value,
      ]);
    }

    // Get transitions available to the current user.
    $valid_transitions = $this->transitionValidation
      ->getValidTransitions($node, $this->currentUser());

    $transition = NULL;

    foreach ($valid_transitions as $candidate) {
      if ($candidate->to()->id() === $status) {
        $transition = $candidate;
        break;
      }
    }

    if (!$transition) {
      return new JsonResponse([
        'error' => sprintf(
          'No valid workflow transition from "%s" to "%s".',
          $current_state,
          $status,
        ),
      ], 400);
    }

    // Save the moderation state and board status in the same revision.
    $node->setNewRevision(TRUE);
    $node->set('moderation_state', $status);
    $node->set('field_status', $status);

    $node->setRevisionLogMessage(
      sprintf(
        'Task status changed from %s to %s via Task Board.',
        $current_state,
        $status,
      ),
    );
    $node->setRevisionUserId($this->currentUser()->id());

    $node->save();

    return new JsonResponse([
      'success' => TRUE,
      'task_id' => $node->id(),
      'status' => $status,
      'moderation_state' => $node->get('moderation_state')->value,
    ]);
  }

  /**
   * Renders a Task in the Full view mode for the board modal.
   */
  public function taskModal(NodeInterface $node): array {
    if ($node->bundle() !== 'task') {
      throw new NotFoundHttpException();
    }

    return $this->entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, 'full');
  }

}
