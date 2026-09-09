<?php

namespace Drupal\drupaljira_board\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides AJAX callbacks for the DrupalJira task board.
 */
final class TaskBoardController extends ControllerBase {

  /**
   * Updates the status of a Task node.
   */
  public function updateStatus(NodeInterface $node, Request $request): JsonResponse {
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

    $node->set('field_status', $status);
    $node->save();

    return new JsonResponse([
      'success' => TRUE,
      'task_id' => $node->id(),
      'status' => $status,
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
