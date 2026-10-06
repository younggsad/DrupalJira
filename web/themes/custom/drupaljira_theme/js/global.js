/**
 * @file
 * Global theme behaviors for DrupalJira.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.drupaljiraGlobal = {
    attach(context) {
      once('drupaljira-global', 'body', context).forEach(() => {
        // Global UI enhancements or initializations can go here.
      });
    },
  };

})(Drupal, once);
