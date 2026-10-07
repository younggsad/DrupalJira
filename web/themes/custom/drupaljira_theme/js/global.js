(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.drupaljiraTables = {
    attach(context) {
      once('jira-table-scroll', '.drupaljira-content-area table', context).forEach((table) => {
        if (table.closest('.table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'table-scroll';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', table.caption?.textContent || Drupal.t('Scrollable table'));
        table.before(wrapper);
        wrapper.append(table);
      });
    },
  };
})(Drupal, once);
