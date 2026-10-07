(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.drupaljiraEditingFrame = {
    attach(context) {
      if (window.parent === window || !document.body.classList.contains('path-frontend-editing')) return;
      once('jira-editor-frame-escape', 'body', context).forEach((body) => {
        body.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || event.defaultPrevented) return;
          if (document.querySelector('.ui-dialog[aria-modal="true"]') || event.target.getAttribute('aria-expanded') === 'true') return;
          const close = window.parent.document.querySelector('#editing-container .editing-container__close');
          if (close) {
            event.preventDefault();
            close.click();
          }
        }, true);
      });
    },
  };

  // Deferred theme scripts can run before Drupal's document-ready callbacks.
  // Bind iframe Escape as soon as its form is parsed; once prevents rebinding.
  if (document.body) Drupal.behaviors.drupaljiraEditingFrame.attach(document);

  Drupal.behaviors.drupaljiraEditingPanel = {
    attach(context) {
      if (!Drupal.frontendEditing) return;
      once('jira-editing-panel-observer', 'body', context).forEach((body) => {
        let opener;
        let panel;

        document.addEventListener('click', (event) => {
          const action = event.target.closest('.frontend-editing-open-sidebar');
          if (action) opener = action;
        }, true);

        const handleEscape = (event) => {
          const owner = event.target.ownerDocument;
          if (event.key !== 'Escape' || event.defaultPrevented || owner.querySelector('.ui-dialog[aria-modal="true"]')) return;
          if (panel?.isConnected) {
            event.preventDefault();
            panel.querySelector('.editing-container__close').click();
          }
        };
        document.addEventListener('keydown', handleEscape);

        const enhance = () => {
          const element = document.getElementById('editing-container');
          if (!element) return;
          once('jira-editing-panel', element).forEach((editor) => {
            panel = editor;
            editor.setAttribute('role', 'dialog');
            editor.setAttribute('aria-label', Drupal.t('Edit content'));
            const close = editor.querySelector('.editing-container__close');
            close.setAttribute('aria-label', Drupal.t('Close editor'));
            close.title = Drupal.t('Close editor');
            const toggle = editor.querySelector('.editing-container__toggle');
            const updateToggle = () => {
              const expanded = editor.classList.contains('editing-container--wide');
              toggle.setAttribute('aria-label', expanded ? Drupal.t('Narrow editor') : Drupal.t('Expand editor'));
              toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            };
            updateToggle();
            toggle.addEventListener('click', updateToggle);
            close.focus();
          });
        };

        const observer = new MutationObserver(() => {
          if (panel && !panel.isConnected) {
            panel = null;
            if (opener?.isConnected) opener.focus();
          }
          enhance();
        });
        observer.observe(body, { childList: true });
        enhance();
      });
    },
  };
})(Drupal, once);
