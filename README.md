CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Differences from Drupal 7 version
 * Maintainers


INTRODUCTION
------------

Registration is a simple, flexible module for allowing and tracking user registrations for events, or just about anything you want people to sign up for. Entity Registration can be integrated with Drupal Commerce to allow fee-based registrations: sell tickets to your stuff! This module is also handy if you want to collect information along with registrations: like shoe-size for a bowling event.

Registration does lots of things you are expecting from a registration system (like allowing you to restrict the number of total registrations for a given event), and other stuff you are crossing your fingers and hoping for (like building in automated reminder messages for your registrants).

 * For a full description of the module visit the [Registration project page](https://www.drupal.org/project/registration).

 * To submit bug reports and feature suggestions, or to track changes, visit the [Registration project issues queue](https://www.drupal.org/project/issues/registration).


REQUIREMENTS
------------

This module requires no modules outside of Drupal core. Upon installation it enables the following core modules if they are not enabled yet:

* Datetime
* Field
* Text
* User
* Workflows

INSTALLATION
------------

Install the Registration module as you would normally install a contributed
Drupal module. Visit [https://www.drupal.org/node/1897420](https://www.drupal.org/node/1897420) for further
information.

Once the module is installed, a new **Registration** field type becomes available in Field UI.

CONFIGURATION
-------------

Configuration for the Drupal 9 version of the module is similar to the Drupal 7 version.

1. Create at least one registration bundle (or type) at /admin/structure/registration-types, much like you would a content type. For example, add a registration type named Conference or Seminar.
1. Add a registration field to any entity type you want to enable registrations for. For example, you may have an Event content type that you want to enable Conference registrations for - add a field to that content type. Provide appropriate default registration settings for the field as needed.
1. Configure the Form Display for the content type you added the registration field to. Typically you would want the registration field to be editable instead of disabled. Indicate whether a Register tab should be displayed for content of the configured type using the field settings widget.
1. Configure the Display for the content type you added the Registration field to.  Choose Registration form, Registration link or Registration type as the field formatter for the registration field. If you choose form, registration is done "inline" on the content type display.  If you choose link then the user registers from a separate page.  If you choose type then you will most likely want to enable the Register tab, otherwise the user will not be able to register.
1. When you add or edit an entity, select the registration type you want to use for the entity.
1. Registrations are now enabled for the entity and you can configure the registration settings via a local task.

The following are optional tasks that work differently compared to the Drupal 7 version of the module (see the next section for a full description of the differences between versions).

1. (Optional) Adjust the default registration states at /admin/config/workflow/workflows. This link is available via the Workflow menu item in the Configuration menu of Drupal administration.
1. (Optional) Adjust general module settings at /admin/structure/registration-settings. This link is available from the main Configuration page of Drupal administation.


DIFFERENCES FROM DRUPAL 7 VERSION
-----------
There are some important differences between the Drupal 7 and Drupal 9 versions of the module.

1. The Drupal 9 version is a complete rewrite of the module and accordingly uses Drupal 9 concepts such as separating Form Display from Field settings, custom plugins, services, class inheritance and dependency injection.
1. The Drupal 9 version uses some PHP 8 programming constructs and accordingly requires PHP 8. This decision was also made because PHP 7 will be deprecated within a few months of the first release of the Drupal 9 version.
1. In the Drupal 9 version registration states are created and configured using the core Workflow module. You can even create your own Workflow and use it in your Registration types if you have a highly custom registration workflow. The default workflow that is created during module installation will work for the most common use cases.
1. The Drupal 7 version includes three submodules - entity access, views and waitlist. In the Drupal 9 version the functionality in the views submodule is included in the main module. The entity access and waitlist submodules have not been created yet and may be added in the future if requested by the community.
1. Commerce integration for the Drupal 9 version of the module via the [Commerce Registration](https://www.drupal.org/project/commerce_registration) module is currently in development, and should be released by the fall of 2022.

MAINTAINERS
-----------

Drupal 9 module:

 * John Oltman - [https://www.drupal.org/u/johnoltman](https://www.drupal.org/u/johnoltman)

Drupal 7 module:

 * Lev Tsypin (levelos) - [https://www.drupal.org/u/levelos](https://www.drupal.org/u/levelos)
 * Jaymz Rhime (wxactly) - [https://www.drupal.org/u/wxactly](https://www.drupal.org/u/wxactly)
 * Gabriel Carleton-Barnes (gcb) - [https://www.drupal.org/u/gcb](https://www.drupal.org/u/gcb)
 * Greg Boggs - [https://www.drupal.org/u/greg-boggs](https://www.drupal.org/u/greg-boggs)
 * Neslee Canil Pinto - [https://www.drupal.org/u/neslee-canil-pinto](https://www.drupal.org/u/neslee-canil-pinto)
 * Brooke Mahoney (loopduplicate) - [https://www.drupal.org/u/loopduplicate](https://www.drupal.org/u/loopduplicate)
