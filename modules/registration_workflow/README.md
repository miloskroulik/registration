CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

Registration Workflow is a submodule of Registration that adds permissions and operations for workflow transitions.

CONFIGURATION
-------------

After enabling this module, permissions in the "Registration Workflow" section of the Permissions page should be set for the appropriate roles.

Users with the appropriate permissions will see actions corresponding to the available transitions when viewing a registration or viewing a registration listing. Currently, permissions provided by this module only control workflow operations, and do not alter the states shown on the registration form.

Site builders can customize the workflow states and transitions using the Drupal core admin interface at Configuration > Workflow.

**Note**: This module triggers a "workflow:transition" event within ECA if you have the [ECA Workflow](https://www.drupal.org/project/eca) module installed.
