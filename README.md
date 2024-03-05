CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Included Add-on Modules
 * Users and Registrations in Views
 * Multilingual Considerations
 * Maintainers


INTRODUCTION
------------

Registration is a flexible module for Drupal 10 that allows tracking user registrations for events, or just about anything you want people to sign up for. Entity Registration can be integrated with Drupal Commerce to allow fee-based registrations: sell tickets to your stuff! This module is also handy if you want to collect information along with registrations: like shoe-size for a bowling event.

Registration does lots of things you are expecting from a registration system (like allowing you to restrict the number of total registrations for a given event), and other stuff you are crossing your fingers and hoping for (like building in automated reminder messages for your registrants).

If you want to sell registrations to your events, use the [Commerce Registration](https://www.drupal.org/project/commerce_registration) module to integrate Registration with Drupal Commerce.

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

After enabling the module, perform the following steps to configure Registration for your site.

1. Create at least one registration bundle (or type) at /admin/structure/registration-types, much like you would a content type. For example, add a registration type named Conference or Seminar.
1. Add a registration field to any entity type you want to enable registrations for. For example, you may have an Event content type that you want to enable Conference registrations for - add a field to that content type. Provide appropriate default registration settings for the field as needed.
1. Configure the Form Display for the content type you added the registration field to. Typically you would want the registration field to be editable instead of disabled. Indicate whether a Register tab should be displayed for content of the configured type using the field settings widget.
1. Configure the Display for the content type you added the Registration field to.  Choose Registration form, Registration link or Registration type as the field formatter for the registration field. If you choose form, registration is done "inline" on the content type display.  If you choose link then the user registers from a separate page.  If you choose type then you will most likely want to enable the Register tab, otherwise the user will not be able to register.
1. When you add or edit an entity, select the registration type you want to use for the entity.
1. Registrations are now enabled for the entity and you can configure the registration settings via a local task.
1. Extend your registration types with additional fields as needed. For example, if your site allows users to register for classes, it may be useful to add biographical data such as First name and Last name fields to your registration type. These fields automatically appear on the registration form.
1. Add registration related permissions to the appropriate roles at /admin/people/permissions.
1. (Optional) Adjust the default registration states at /admin/config/workflow/workflows. This link is available via the Workflow menu item in the Configuration menu of Drupal administration. If you want administrators to be able to edit the state for new or existing registrations, you should configure the "show on form" setting for the appropriate states.
1. (Optional) Adjust general module settings at /admin/structure/registration-settings. This link is available from the main Configuration page of Drupal administration. If you want registration related emails to be sent as HTML, you need to visit this page.


INCLUDED ADD-ON MODULES
-----------

The Registration module includes the following submodules that can be enabled to provide additional functionality.

1. Registration Administrative Overrides - allow system administrators to override limits within registration settings.
1. Registration Confirmation - send confirmation emails when registrations are completed.
1. Registration Inline Entity Form - allow registration settings to be edited on the host entity edit form.
1. Registration Purger - automatically delete registrations and registration settings for a host entity that is deleted.
1. Registration Scheduled Action - setup scheduled emails based on registration settings dates in your system.
1. Registration Wait List - allow overflow registrations to a wait list.
1. Registration Workflow - add permissions and operations for workflow transitions and integrate with [ECA Workflow](https://www.drupal.org/project/eca) transitions.

See the README file in each submodule folder for more information.


USERS AND REGISTRATIONS IN VIEWS
-----------
If you have the Views module installed, and want to create an administrative listing of Users and their associated registrations, the reverse relationship from a User entity to their registrations is not available using the Registration module "out of the box", due to core issue #2706431. To enable this relationship, install the [Entity API](https://www.drupal.org/project/entity) contributed module. Then use the **Registration using user_uid** relationship (with description *Relate each Registration with a user_uid field set to the user*) to associate Users to their registrations. The relationship named **User registration** (with description *Relate users to their registrations*) is for the unlikely case that the User entity is a host entity for registrations, and is not suitable for this type of listing.

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
