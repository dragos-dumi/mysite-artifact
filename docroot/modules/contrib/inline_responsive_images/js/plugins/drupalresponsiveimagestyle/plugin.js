/**
 * @file
 * Drupal Responsive Image Style plugin.
 *
 * This alters the existing CKEditor image2 widget plugin, which is already
 * altered by the Drupal Image plugin, to data-responsive-image-style attribute
 * to be set.
 *
 * @ignore
 */

(function (CKEDITOR) {

  "use strict";

  CKEDITOR.plugins.add('drupalresponsiveimagestyle', {
    requires: 'drupalimage',

    beforeInit: function (editor) {
      // Override the image2 widget definition to handle the additional
      // data-responsive-image-style attributes.
      editor.on('widgetDefinition', function (event) {
        var widgetDefinition = event.data;
        if (widgetDefinition.name !== 'image') {
          return;
        }
        // Override default features definitions for drupalresponsiveimagestyle.
        CKEDITOR.tools.extend(widgetDefinition.features, {
          responsiveimage: {
            requiredContent: 'img[data-responsive-image-style]'
          }
        }, true);

        // Override requiredContent & allowedContent.
        var requiredContent = widgetDefinition.requiredContent.getDefinition();
        requiredContent.attributes['data-responsive-image-style'] = '';
        widgetDefinition.requiredContent = new CKEDITOR.style(requiredContent);
        widgetDefinition.allowedContent.img.attributes['!data-responsive-image-style'] = true;

        // Override downcast().
        var originalDowncast = widgetDefinition.downcast;
        widgetDefinition.downcast = function (element) {
          var img = originalDowncast.call(this, element);
          if (!img) {
            img = findElementByName(element, 'img');
          }
          img.attributes['data-responsive-image-style'] = this.data['data-responsive-image-style'];
          return img;
        };

        // Override upcast().
        var originalUpcast = widgetDefinition.upcast;
        widgetDefinition.upcast = function (element, data) {
          if (element.name !== 'img' || !element.attributes['data-entity-type'] || !element.attributes['data-entity-uuid']) {
            return;
          }
          // Don't initialize on pasted fake objects.
          else if (element.attributes['data-cke-realelement']) {
            return;
          }

          // Parse the data-responsive-image-style attribute.
          data['data-responsive-image-style'] = element.attributes['data-responsive-image-style'];

          // Upcast after parsing so correct element attributes are parsed.
          element = originalUpcast.call(this, element, data);

          return element;
        };

        // Protected; keys of the widget data to be sent to the Drupal dialog.
        // Append to the values defined by the drupalimage plugin.
        // @see core/modules/ckeditor/js/plugins/drupalimage/plugin.js
        CKEDITOR.tools.extend(widgetDefinition._mapDataToDialog, {
          'data-responsive-image-style': 'data-responsive-image-style',
        });
      // Low priority to ensure drupalimage's event handler runs first.
      }, null, null, 20);
    }
  });

  /**
   * Finds an element by its name.
   *
   * Function will check first the passed element itself and then all its
   * children in DFS order.
   *
   * @param {CKEDITOR.htmlParser.element} element
   *   The element to search.
   * @param {string} name
   *   The element name to search for.
   *
   * @return {?CKEDITOR.htmlParser.element}
   *   The found element, or null.
   */
  function findElementByName(element, name) {
    if (element.name === name) {
      return element;
    }

    var found = null;
    element.forEach(function (el) {
      if (el.name === name) {
        found = el;
        // Stop here.
        return false;
      }
    }, CKEDITOR.NODE_ELEMENT);
    return found;
  }

})(CKEDITOR);
