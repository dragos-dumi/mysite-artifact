/*
--------------------------------------------------------------------------
(c) 2007 Lawrence Akka
 - jquery version of the spamspan code (c) 2006 SpamSpan (www.spamspan.com)

This program is distributed under the terms of the GNU General Public
Licence version 2, available at http://www.gnu.org/licenses/gpl.txt
--------------------------------------------------------------------------
*/

(function ($) {
  'use strict';
  // load SpamSpan
  Drupal.behaviors.spamspan = {
    attach: function (context) {
      // get each span with class spamspan
      $("span.spamspan", context).each(function (index) {
        // Replace each <span class="o"></span> with .
        if ($('span.o', this).length) {
          $('span.o', this).replaceWith('.');
        }

        // For each selected span, set mail to the relevant value, removing spaces
        var _mail = ($("span.u", this).text() +
        "@" +
        $("span.d", this).text())
        .replace(/\s+/g, '');

        // Build the mailto URI
        var _mailto = "mailto:" + _mail;
        if ($('span.h', this).length) {
          // Find the header text, and remove the round brackets from the start and end
          var _headerText = $("span.h", this).text().replace(/^ ?\((.*)\) ?$/, "$1");
          // split into individual headers, and return as an array of header=value pairs
          var _headers = $.map(_headerText.split(/, /), function (n, i) {
            return (n.replace(/: /, "="));
          });

          var _headerstring = _headers.join('&');
          _mailto += _headerstring ? ("?" + _headerstring) : '';
        }

        // Find the anchor content, and remove the round brackets from the start and end
        var _anchorContent = $("span.t", this).html();
        if (_anchorContent) {
          _anchorContent = _anchorContent.replace(/^ ?\((.*)\) ?$/, "$1");
        }

        // create the <a> element, and replace the original span contents

        // check for extra <a> attributes
        var _attributes = $("span.e", this).html();
        var _tag = "<a></a>";
        if (_attributes) {
          _tag = "<a " + _attributes.replace("<!--", "").replace("-->", "") + "></a>";
        }

        $(this).after(
          $(_tag)
          .attr("href", _mailto)
          .html(_anchorContent ? _anchorContent : _mail)
          .addClass("spamspan")
        ).remove();
      });
    }
  };
}) (jQuery);