# Display Field Copy
Make display copies of fields.

## Introduction
Display Field Copy provides the ability to make a display copy of a field with
[Display Suite](https://www.drupal.org/project/ds). The copy of the field can
use a different formatter (with different config) than the single field that is
currently available in core.

## Purpose
If a site has a single entity reference field and the display needs the list
rendered on the page twice (once as a rendered entity and once as a list of
links). This module could be used to create a copy of the field and apply a
different formatter to it.

This module does not have a copy limit. Theoretically, an unlimited number of
copies could be created. It should work for any field that has at least one
formatter available.

## Installation
Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/documentation/install/modules-themes/modules-8
for further information.

## Requirements
* [Display Suite](https://www.drupal.org/project/ds)

## Setup
1. Go to https://example.com/admin/structure/ds/fields
2. Click "Create a copy of a field" and create a display copy.
3. Go to "Manage Display" of the entity of the field you copied (i.e.
   http://example.com/admin/structure/types/manage/article/display).
4. Configure the field copy.

## Maintainers
Current maintainers:
* David Barratt ([davidwbarratt](https://www.drupal.org/u/davidwbarratt))

## Sponsors
Current sponsors:
* [Sail Venice](https://www.drupal.org/sail-venice)
