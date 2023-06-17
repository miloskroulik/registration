CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Users and Registrations in Views
 * Differences from Drupal 7 version
 * Maintainers


INTRODUCTION
------------

Registration is a simple, flexible module for allowing and tracking user registrations for events, or just about anything you want people to sign up for. Entity Registration can be integrated with Drupal Commerce to allow fee-based registrations: sell tickets to your stuff! This module is also handy if you want to collect information along with registrations: like shoe-size for a bowling event.

Registration does lots of things you are expecting from a registration system (like allowing you to restrict the number of total registrations for a given event), and other stuff you are crossing your fingers and hoping for (like building in automated reminder messages for your registrants).

If you want to sell registrations to your events, use the [Commerce Registration](https://www.drupal.org/project/commerce_registration) module to integrate Registration with Drupal Commerce.  Commerce Registration has a release that is compatible with Drupal 9 and 10.

 * For a full description of this module visit the [Registration project page](https://www.drupal.org/project/registration).

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

Configuration for the Drupal 10 version of the module is similar to the Drupal 7 version.

1. Create at least one registration bundle (or type) at /admin/structure/registration-types, much like you would a content type. For example, add a registration type named Conference or Seminar.
1. Add a registration field to any entity type you want to enable registrations for. For example, you may have an Event content type that you want to enable Conference registrations for - add a field to that content type. Provide appropriate default registration settings for the field as needed.
1. Configure the Form Display for the content type you added the registration field to. Typically you would want the registration field to be editable instead of disabled. Indicate whether a Register tab should be displayed for content of the configured type using the field settings widget.
1. Configure the Display for the content type you added the Registration field to.  Choose Registration form, Registration link or Registration type as the field formatter for the registration field. If you choose form, registration is done "inline" on the content type display.  If you choose link then the user registers from a separate page.  If you choose type then you will most likely want to enable the Register tab, otherwise the user will not be able to register.
1. When you add or edit an entity, select the registration type you want to use for the entity.
1. Registrations are now enabled for the entity and you can configure the registration settings via a local task.
1. Extend your registration types with additional fields as needed. For example, if your site allows users to register for classes, it may be useful to add biographical data such as First name and Last name fields to your registration type. These fields automatically appear on the registration form.

The following are optional tasks that work differently compared to the Drupal 7 version of the module (see the next section for a full description of the differences between versions).

1. (Optional) Adjust the default registration states at /admin/config/workflow/workflows. This link is available via the Workflow menu item in the Configuration menu of Drupal administration.
1. (Optional) Adjust general module settings at /admin/structure/registration-settings. This link is available from the main Configuration page of Drupal administration. If you want registration related emails to be sent as HTML, you need to visit this page.
1. (Optional) Enable the Registration Confirmation submodule to send confirmation emails when registrations are completed. This is configured on the registration type edit form.
1. (Optional) Enable the Registration Purger submodule to automatically delete registrations and registration settings for a host entity that is deleted.
1. (Optional) Enable the Registration Wait List submodule to allow overflow registrations to a wait list.
1. (Optional) Enable the Registration Workflow submodule to add permissions and operations for workflow transitions and integrate with [ECA Workflow](https://www.drupal.org/project/eca) transitions.
1. (Optional) Enable the Registration Scheduled Action submodule to setup scheduled emails based on registration settings dates in your system. See the README file in that submodule folder for more information.


USERS AND REGISTRATIONS IN VIEWS
-----------
If you have the Views module installed, and want to create an administrative listing of Users and their associated registrations, the reverse relationship from a User entity to their registrations is not available using the Registration module "out of the box", due to core issue #2706431. To enable this relationship, install the [Entity API](https://www.drupal.org/project/entity) contributed module. Then use the **Registration using user_uid** relationship (with description *Relate each Registration with a user_uid field set to the user*) to associate Users to their registrations. The relationship named **User registration** (with desciption *Relate users to their registrations*) is for the unlikely case that the User entity is a host entity for registrations, and is not suitable for this type of listing.

DIFFERENCES FROM DRUPAL 7 VERSION
-----------
There are some important differences between the Drupal 7 and Drupal 10 versions of the module.

1. The Drupal 10 version is a complete rewrite of the module and accordingly uses advanced Drupal concepts such as separating Form Display from Field settings, custom plugins, services, class inheritance and dependency injection.
1. In the Drupal 10 version registration states are created and configured using the core Workflows module. You can even create your own Workflow and use it in your Registration types if you have a highly custom registration workflow. The default workflow that is created during module installation will work for the most common use cases.
1. In the Drupal 10 version registration listings are provided as customizable views if you have the Views module installed. Listings are presented in traditional tables if the Views module is not installed.
1. The Drupal 7 version includes three submodules - entity access, views and wait list. In the Drupal 10 version the functionality in the views submodule is included in the main module. The entity access submodule has not been created yet and may be added in the future if requested by the community. The Drupal 10 version includes the wait list submodule, a confirmation submodule that sends emails when registrations are completed, a purger submodule for host entity deletion, a workflow submodule that adds permissions and operations for workflow transitions, and a scheduled actions submodule allowing you to send emails at scheduled intervals.

MULTILINGUAL CONSIDERATIONS
-----------

The Drupal 10 version includes full support for multilingual sites. Although registrations are not translatable entities, each registration has a language field indicating the language used to create it. This allows you to craft different reminder emails for each language and target just the registrants for a given language.

Registration settings can also vary per language. In many cases, untranslatable settings fields, such as the capacity for a given registration event, should be the same across all language variants. Visit the global settings page at /admin/structure/registration-settings to control how registration settings are handled across languages. This feature is only visible to sites with multiple languages installed.

Note that some listings, such as the Registration Summary at /admin/people/registration-summary, only display registrations in the current interface language by default. This may not be appropriate for every multilingual site. You can customize the listings as needed for your use case.

MAINTAINERS
-----------

Drupal 10 module:

 * John Oltman - [https://www.drupal.org/u/johnoltman](https://www.drupal.org/u/johnoltman)

Drupal 7 module:

 * Lev Tsypin (levelos) - [https://www.drupal.org/u/levelos](https://www.drupal.org/u/levelos)
 * Jaymz Rhime (wxactly) - [https://www.drupal.org/u/wxactly](https://www.drupal.org/u/wxactly)
 * Gabriel Carleton-Barnes (gcb) - [https://www.drupal.org/u/gcb](https://www.drupal.org/u/gcb)
 * Greg Boggs - [https://www.drupal.org/u/greg-boggs](https://www.drupal.org/u/greg-boggs)
 * Neslee Canil Pinto - [https://www.drupal.org/u/neslee-canil-pinto](https://www.drupal.org/u/neslee-canil-pinto)
 * Brooke Mahoney (loopduplicate) - [https://www.drupal.org/u/loopduplicate](https://www.drupal.org/u/loopduplicate)
