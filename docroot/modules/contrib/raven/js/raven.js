/**
 * @file
 * Configures Raven.js with the public DSN and extra options.
 */
(function (drupalSettings, Raven) {

  'use strict';

  Raven.config(drupalSettings.raven.dsn, drupalSettings.raven.options).install();

})(drupalSettings, Raven);
