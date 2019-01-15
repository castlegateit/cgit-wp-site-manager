<?php

namespace Cgit\SiteManager;

class Plugin
{
    /**
     * Custom role name
     *
     * @var string
     */
    private $role = 'cgit_site_manager';

    /**
     * Custom role label
     *
     * @var string
     */
    private $label = 'Site Manager';

    /**
     * Admin role name
     *
     * @var string
     */
    private $adminRole = 'administrator';

    /**
     * Construct
     *
     * @return void
     */
    public function __construct()
    {
        $plugin = CGIT_SITE_MANAGER_PLUGIN;

        $this->name = apply_filters('cgit_site_manager_role_name', $this->name);
        $this->label = apply_filters('cgit_site_manager_role_label', $this->label);

        register_activation_hook($plugin, [$this, 'createRole']);

        add_action('current_screen', [$this, 'blockUserEdit']);
        add_action('current_screen', [$this, 'blockThemeEdit']);
        add_action('delete_user', [$this, 'blockUserDelete']);
        add_action('admin_menu', [$this, 'hideThemePages']);

        add_filter('editable_roles', [$this, 'blockEditableRoles']);
    }

    /**
     * Create site manager user role
     *
     * The new user role should have all the capabilities of the editor role
     * plus the ability to edit users and navigation menus.
     *
     * @return void
     */
    public function createRole()
    {
        $role = apply_filters('cgit_site_manager_base_role', 'editor');
        $caps = array_merge(get_role($role)->capabilities, [
            // Edit users
            'create_users' => true,
            'delete_users' => true,
            'edit_users' => true,
            'list_users' => true,
            'promote_users' => true,
            'remove_users' => true,

            // Edit menus
            'edit_theme_options' => true,
        ]);

        // Site manager capabilities can be edited via a filter. This will only
        // take effect when the plugin is reactivated.
        $caps = apply_filters('cgit_site_manager_capabilities', $caps);

        remove_role($this->role);
        add_role($this->role, $this->label, $caps);
    }

    /**
     * Block editable roles
     *
     * Prevent site managers from creating admins by removing the admin role
     * from the list of editable roles.
     *
     * @param array $roles
     * @return array
     */
    public function blockEditableRoles($roles)
    {
        if (!$this->isSiteManager()) {
            return $roles;
        }

        return array_diff_key($roles, [
            $this->adminRole => null,
        ]);
    }

    /**
     * Block user edit
     *
     * Prevent site managers from editing admin users by restricting access to
     * the user edit screen.
     *
     * @param WP_Screen $screen
     * @return void
     */
    public function blockUserEdit($screen)
    {
        $user_id = $this->getUserIdFromScreen($screen);

        if (!$user_id || !$this->isSiteManager() || !$this->isAdmin($user_id)) {
            return;
        }

        $this->nope();
    }

    /**
     * Block theme edit
     *
     * Prevent site managers from making changes to the theme and widgets by
     * restricting access to pages in the Appearance menu.
     *
     * @param WP_Screen $screen
     * @return void
     */
    public function blockThemeEdit($screen)
    {
        $blocked = apply_filters('cgit_site_manager_blocked_screens', [
            'themes',
            'customize',
            'widgets',
        ]);

        if (!$this->isSiteManager() || !in_array($screen->base, $blocked)) {
            return;
        }

        $this->nope();
    }

    /**
     * Block user delete
     *
     * Prevent site managers from deleting admin users.
     *
     * @param integer $user_id
     * @return void
     */
    public function blockUserDelete($user_id)
    {
        if (!$this->isSiteManager() || !$this->isAdmin($user_id)) {
            return;
        }

        $this->nope();
    }

    /**
     * Hide theme menu pages
     *
     * @return void
     */
    public function hideThemePages()
    {
        global $submenu;

        $themes = 'themes.php';
        $path = 2;

        $blocked = apply_filters('cgit_site_manager_blocked_pages', [
            'customize.php',
            'themes.php',
            'widgets.php',
        ]);

        if (!$this->isSiteManager() || !isset($submenu[$themes])) {
            return;
        }

        foreach ($submenu[$themes] as $key => $item) {
            if (!isset($item[$path])) {
                continue;
            }

            $page = parse_url($item[$path], PHP_URL_PATH);

            if (in_array($page, $blocked)) {
                unset($submenu[$themes][$key]);
            }
        }
    }

    /**
     * Return user instance
     *
     * @param mixed $user
     * @return mixed
     */
    private function getUserInstance($user = null)
    {
        if (is_a($user, 'WP_User')) {
            return $user;
        }

        if (is_null($user)) {
            return wp_get_current_user();
        }

        if (is_numeric($user)) {
            return get_user_by('id', $user);
        }

        if (is_string($user)) {
            if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
                return get_user_by('email', $user);
            }

            return get_user_by('login', $user);
        }
    }

    /**
     * Return user role
     *
     * @param mixed $user
     * @return mixed
     */
    private function getUserRole($user = null)
    {
        $user = $this->getUserInstance($user);

        if (!is_object($user) || !property_exists($user, 'roles') ||
            !isset($user->roles[0])) {
            return;
        }

        return $user->roles[0];
    }

    /**
     * Is the (current) user a site manager?
     *
     * @param mixed $user
     * @return boolean
     */
    private function isSiteManager($user = null)
    {
        return $this->getUserRole($user) == $this->role;
    }

    /**
     * Is the (current) user an admin?
     *
     * @param mixed $user
     * @return boolean
     */
    private function isAdmin($user = null)
    {
        return $this->getUserRole($user) == $this->adminRole;
    }

    /**
     * Return user ID based on screen
     *
     * @param WP_Screen $screen
     * @return integer
     */
    private function getUserIdFromScreen($screen)
    {
        if ($screen->base == 'user-edit' && isset($_GET['user_id'])) {
            return (int) $_GET['user_id'];
        }

        if ($screen->base == 'users' && isset($_GET['user'])) {
            return (int) $_GET['user'];
        }

        return 0;
    }

    /**
     * Block access and show error message
     *
     * @param string $message
     * @return void
     */
    private function nope($message = null)
    {
        if (is_null($message)) {
            $message = 'Access denied. You must be an administrator to view this page.';
        }

        $title = apply_filters('cgit_site_manager_error_title', 'Access denied');
        $message = apply_filters('cgit_site_manager_error_message', $message);

        wp_die($message, $title, 403);
    }
}
