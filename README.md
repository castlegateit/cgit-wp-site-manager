# Castlegate IT WP Site Manager

Castlegate IT WP Site Manager is a WordPress plugin that adds a site manager user role with the same capabilities as an editor, plus the ability to edit navigation menus and make changes to non-administrator users.

## Filters

*   `cgit_site_manager_role_name` filters the role name. Default value `cgit_site_manager`.
*   `cgit_site_manager_role_label` filters the role label. Default value `Site Manager`.
*   `cgit_site_manager_base_role` filters the user role used as a basis for the site manager user role. Default value `editor`.
*   `cgit_site_manager_capabilities` filters the array of capabilities assigned to the site manager user role.
*   `cgit_site_manager_blocked_screens` filters the list of screen names that are available to administrators but not site managers.
*   `cgit_site_manager_blocked_pages` filters the list of menu pages in the WordPress dashboard that are visible to administrators but not site managers.
*   `cgit_site_manager_error_title` filters the error page title. Default value `Access denied`.
*   `cgit_site_manager_error_message` filters the error message displayed when a site manager tries to visit a page that is only available to administrators.

Note that user capabilities are stored in the database. Some of these filters may not take effect until the plugin is restarted.
