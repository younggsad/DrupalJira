(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.taskDescriptionEdit = {
    attach(context) {
      once(
        'task-description-edit',
        '.task-description-edit .frontend-editing__action--edit',
        context,
      ).forEach((link) => {
        link.addEventListener('click', (event) => {
          event.preventDefault();

          if (
            Drupal.frontendEditing &&
            typeof Drupal.frontendEditing.editingClick === 'function'
          ) {
            Drupal.frontendEditing.editingClick(event);
          }
        });
      });
    },
  };
})(Drupal, once);