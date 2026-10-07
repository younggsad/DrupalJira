(function (Drupal, once, $) {
  'use strict';

  const dialogWidth = (settings) => {
    const classes = settings?.classes?.['ui-dialog'] || '';
    if (classes.includes('media-library-widget-modal')) return 1080;
    if (classes.includes('drupaljira-modal-task')) return 960;
    return null;
  };

  Drupal.behaviors.drupaljiraDialogs = {
    attach(context) {
      once('jira-dialog-sizing', 'body', context).forEach(() => {
        document.addEventListener('dialog:beforecreate', (event) => {
          const width = dialogWidth(event.settings);
          if (!width) return;
          event.settings.width = Math.min(width, window.innerWidth - 32);
          event.settings.maxHeight = window.innerHeight - 32;
          event.settings.draggable = false;
          event.settings.resizable = false;
          // Core's debounced resize may fire after a rapidly closed widget is
          // removed. Resize these application dialogs synchronously instead.
          event.settings.autoResize = false;
        });
        document.addEventListener('dialog:aftercreate', (event) => {
          const width = dialogWidth(event.settings);
          if (!width) return;
          const element = event.target;
          const resize = () => {
            if (!event.dialog.open || !element.isConnected) return;
            $(element).dialog('option', {
              width: Math.min(width, window.innerWidth - 32),
              maxHeight: window.innerHeight - 32,
              position: { my: 'center', at: 'center', of: window },
            });
            element.dispatchEvent(new CustomEvent('dialogContentResize', { bubbles: true }));
          };
          window.addEventListener('resize', resize);
          element.addEventListener('dialog:beforeclose', () => {
            window.removeEventListener('resize', resize);
          }, { once: true });
        });
      });
    },
  };
})(Drupal, once, jQuery);
