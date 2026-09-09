(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.taskBoard = {
    attach(context) {
      once('task-board', '.task-board__columns', context).forEach((board) => {
        const cards = board.querySelectorAll('.task-card');
        const columns = board.querySelectorAll('.task-board__column');

        cards.forEach((card) => {
          card.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', card.dataset.taskId);
            event.dataTransfer.effectAllowed = 'move';
            card.classList.add('task-card--dragging');
          });

          card.addEventListener('dragend', () => {
            card.classList.remove('task-card--dragging');
          });

          card.addEventListener('click', async (event) => {
            if (event.target.closest('.frontend-editing-actions')) {
              return;
            }

            if (card.classList.contains('task-card--dragging')) {
              return;
            }

            const link = event.target.closest('a');

            if (!link || !card.contains(link)) {
              return;
            }

            event.preventDefault();

            const taskId = card.dataset.taskId;

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

              const dialog = Drupal.dialog(
                `<div class="task-board__modal-content">${html}</div>`,
                {
                  title: link.textContent.trim(),
                  modal: true,
                  width: '70%',
                  maxWidth: '900px',
                  buttons: [],
                  close: function () {
                    dialog.destroy();
                  },
                },
              );

              dialog.showModal();
              Drupal.attachBehaviors(dialog.$element[0]);
            }
            catch (error) {
              console.error('DrupalJira Board:', error);
            }
          });
        });

        columns.forEach((column) => {
          const cardsContainer = column.querySelector('.task-board__cards');

          column.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('task-board__column--drag-over');
          });

          column.addEventListener('dragleave', (event) => {
            if (!column.contains(event.relatedTarget)) {
              column.classList.remove('task-board__column--drag-over');
            }
          });

          column.addEventListener('drop', async (event) => {
            event.preventDefault();

            const taskId = event.dataTransfer.getData('text/plain');
            const newStatus = column.dataset.status;

            column.classList.remove('task-board__column--drag-over');

            const card = board.querySelector(
              `.task-card[data-task-id="${CSS.escape(taskId)}"]`,
            );

            if (!card) {
              return;
            }

            const previousContainer = card.parentElement;

            cardsContainer.appendChild(card);

            try {
              const tokenResponse = await fetch(
                Drupal.url('session/token'),
              );

              if (!tokenResponse.ok) {
                throw new Error('Failed to get CSRF token.');
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
                throw new Error('Failed to update task status.');
              }

              const result = await response.json();

              if (!result.success) {
                throw new Error(
                  'Task status update was not successful.',
                );
              }
            }
            catch (error) {
              console.error('DrupalJira Board:', error);
              previousContainer.appendChild(card);
            }
          });
        });
      });
    },
  };
})(Drupal, once);