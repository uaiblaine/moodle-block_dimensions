moodle-block_dimensions
=======================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-block_dimensions/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-block_dimensions/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

A Moodle block plugin that displays competency cards from users' active learning plans, providing quick access to competency progress tracking via the local_dimensions plugin.


Requirements
------------

This plugin requires Moodle 4.5+

Additionally, this plugin has the following dependencies:
- tool_lp (core competency)
- local_dimensions


Motivation for this plugin
--------------------------

The standard Moodle Learning Plans block provides limited visibility into competency progress. This plugin was created to:

1. Provide a visual overview of competencies from active learning plans using customizable cards
2. Integrate with the local_dimensions plugin for detailed progress tracking
3. Offer a more engaging user experience with custom card images for each competency


Installation
------------

Install the plugin like any other plugin to folder
/blocks/dimensions

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it is ready to use without the need for any configuration.

Simply add the "Dimensions" block to the Dashboard or any course page. The block will automatically display competency cards from the user's active learning plans.

**Features:**
- Displays competency cards with custom images (configured via local_dimensions custom fields)
- Only shows competencies that have linked courses
- Links directly to the local_dimensions view-plan page for detailed progress tracking
- Filters duplicates when competencies appear in multiple plans

If you want to learn more about using block plugins in Moodle, please see https://docs.moodle.org/en/Blocks.


Capabilities
------------

This plugin introduces these additional capabilities:

### block/dimensions:addinstance
Allows adding a new Dimensions block to a page.
By default, this capability is granted to managers only.

### block/dimensions:myaddinstance
Allows adding a new Dimensions block to the user's Dashboard.
By default, this capability is granted to all authenticated users.


Scheduled Tasks
---------------

This plugin does not add any additional scheduled tasks.


How this plugin works
---------------------

1. When a user views the block, it retrieves all active learning plans for that user
2. For each plan, it fetches the associated competencies
3. Competencies without linked courses are filtered out
4. Duplicate competencies (appearing in multiple plans) are deduplicated
5. For each competency, it attempts to load a custom card image from the local_dimensions custom field
6. The block renders competency cards linking to the local_dimensions view-plan page


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/uaiblaine/moodle-block_dimensions


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/uaiblaine   /moodle-block_dimensions/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Please issue feature proposals on Github:
https://github.com/uaiblaine/moodle-block_dimensions/issues

Please create pull requests on Github:
https://github.com/uaiblaine/moodle-block_dimensions/pulls


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.


Translating this plugin
-----------------------

This Moodle plugin is shipped with English and Brazilian Portuguese language packs. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Privacy
-------

The Dimensions block only shows data stored in other locations. It does not store any personal data itself.


Maintainers
-----------

The plugin is maintained by\
Anderson Blaine


Copyright
---------

The copyright of this plugin is held by\
Anderson Blaine

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
