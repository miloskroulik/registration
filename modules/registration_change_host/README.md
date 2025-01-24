CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration


INTRODUCTION
------------

This module provides support for changing the host for existing registrations. It allows any user with the appropriate permissions to select a new host from a list of possible hosts and then make additional edits to the registration.

This is a developer module. No possible hosts are provided by default. You will need an additional custom or contributed module to provide a list of possible hosts.


CONFIGURATION
-------------

1. Enable the module at Administration > Extend.
2. Give a 'change host' permission to the appropriate roles. A role must have both change host and edit access to a registration to change the host.
3. Install an additional module that provides a list of possible hosts. For example, [Commerce Registration](https://www.drupal.org/project/commerce_registration) provides other variations of the same product as possible hosts.
