(function (Drupal, once, $) {
  'use strict';

  Drupal.behaviors.taskBoard = {
    attach(context) {
      once('task-board', '.task-board__columns', context).forEach((board) => {
        const feedback = board.parentElement.querySelector('.task-board__feedback');
        const announce = (message) => { if (feedback) feedback.textContent = message; };
        const refreshColumns = () => {
          board.querySelectorAll('.task-board__column').forEach((column) => {
            const count = column.querySelectorAll('.task-card').length;
            column.querySelector('.task-board__count').textContent = count;
            column.querySelector('.task-board__empty').hidden = count > 0;
            column.classList.toggle('task-board__column--empty', count === 0);
          });
        };
        const columns = board.querySelectorAll('.task-board__column');

        const openTaskModal = async (card) => {
          const taskId = card.dataset.taskId;

          if (!taskId) {
            return;
          }

          if (card.getAttribute('aria-busy') === 'true') return;
          card.setAttribute('aria-busy', 'true');
          announce(Drupal.t('Loading task…'));
          try {
            const response = await fetch(
              Drupal.url(`task/${taskId}/modal`),
              {
                headers: {
                  'X-Requested-With': 'XMLHttpRequest',
                },
              },
            );

            if (!response.ok) {
              throw new Error('Failed to load task.');
            }

            const html = await response.text();

            const title =
              card.querySelector('.node__title, .field--name-title')
                ?.textContent.trim() || Drupal.t('Task');

            const element = document.createElement('div');
            element.className = 'task-board__modal-content';
            element.innerHTML = html;
            document.body.append(element);
            const dialog = Drupal.dialog(
              element,
              {
                title,
                modal: true,
                width: Math.min(960, window.innerWidth - 32),
                classes: { 'ui-dialog': 'drupaljira-modal-task' },
                buttons: [],
                close() {
                  dialog.close();
                  Drupal.detachBehaviors(element, null, 'unload');
                  $(element).remove();
                  card.focus();
                },
              },
            );

            dialog.showModal();

            Drupal.attachBehaviors(element);
            announce('');
          }
          catch (error) {
            announce(Drupal.t('Unable to load this task. Please try again.'));
          }
          finally {
            card.removeAttribute('aria-busy');
          }
        };

        const interactiveTarget = (event) => {
          const interactive = event.target.closest('a, button, input, textarea, select, .frontend-editing-actions');
          return interactive && !interactive.closest('.node__title');
        };
        board.addEventListener('click', (event) => {
          const card = event.target.closest('.task-card');
          if (!card || card.classList.contains('task-card--dragging') || interactiveTarget(event)) return;
          event.preventDefault();
          openTaskModal(card);
        });
        board.addEventListener('keydown', (event) => {
          const card = event.target.closest('.task-card');
          if (!card || !['Enter', ' '].includes(event.key) || interactiveTarget(event)) return;
          event.preventDefault();
          openTaskModal(card);
        });
        board.addEventListener('dragstart', (event) => {
          const card = event.target.closest('.task-card');
          if (!card || !card.dataset.taskId || card.getAttribute('aria-busy') === 'true') return;
          event.dataTransfer.setData('text/plain', card.dataset.taskId);
          event.dataTransfer.effectAllowed = 'move';
          card.classList.add('task-card--dragging');
        });
        board.addEventListener('dragend', (event) => {
          event.target.closest('.task-card')?.classList.remove('task-card--dragging');
        });

        /* Column drop listeners preserve the existing status endpoint. */
        columns.forEach((column) => {
          const cardsContainer = column.querySelector(
            '.task-board__cards',
          );

          if (!cardsContainer) {
            return;
          }

          column.addEventListener('dragover', (event) => {
            event.preventDefault();

            event.dataTransfer.dropEffect = 'move';

            column.classList.add(
              'task-board__column--drag-over',
            );
          });

          column.addEventListener('dragleave', (event) => {
            if (!column.contains(event.relatedTarget)) {
              column.classList.remove(
                'task-board__column--drag-over',
              );
            }
          });

          column.addEventListener('drop', async (event) => {
            event.preventDefault();

            const taskId =
              event.dataTransfer.getData('text/plain');

            const newStatus = column.dataset.status;

            column.classList.remove(
              'task-board__column--drag-over',
            );

            if (!taskId || !newStatus) {
              return;
            }

            const card = board.querySelector(
              `.task-card[data-task-id="${CSS.escape(taskId)}"]`,
            );

            if (!card) {
              return;
            }

            if (card.getAttribute('aria-busy') === 'true') return;
            const previousContainer = card.parentElement;
            const previousSibling = card.nextSibling;

            if (previousContainer === cardsContainer) {
              return;
            }

            cardsContainer.appendChild(card);
            card.setAttribute('aria-busy', 'true');
            refreshColumns();
            announce(Drupal.t('Updating task status…'));

            try {
              const tokenResponse = await fetch(
                Drupal.url('session/token'),
              );

              if (!tokenResponse.ok) {
                throw new Error(
                  'Failed to get CSRF token.',
                );
              }

              const csrfToken = await tokenResponse.text();

              const response = await fetch(
                Drupal.url(`task/${taskId}/status`),
                {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                  },
                  body: JSON.stringify({
                    status: newStatus,
                  }),
                },
              );

              if (!response.ok) {
                throw new Error(
                  'Failed to update task status.',
                );
              }

              const result = await response.json();

              if (!result.success) {
                throw new Error(
                  'Task status update was not successful.',
                );
              }
              announce(Drupal.t('Task status updated.'));
            }
            catch (error) {
              previousContainer.insertBefore(card, previousSibling?.parentNode === previousContainer ? previousSibling : null);
              announce(Drupal.t('Unable to update status. The task was restored.'));
            }
            finally {
              card.removeAttribute('aria-busy');
              refreshColumns();
            }
          });
        });
      });
    },
  };
})(Drupal, once, jQuery);