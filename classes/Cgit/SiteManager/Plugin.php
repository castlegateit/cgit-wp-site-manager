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
     * User-related capabilities
     *
     * @var array
     */
    private $userCapabilities = [
        'create_users' => true,
        'delete_users' => true,
        'edit_users' => true,
        'list_users' => true,
        'promote_users' => true,
        'remove_users' => true,
    ];

    /**
     * Theme-related capabilities
     *
     * @var array
     */
    private $themeCapabilities = [
        'edit_theme_options' => true,
    ];

    /**
     * Construct
     *
     * @return void
     */
    public function __construct()
    {
        $plugin = CGIT_SITE_MANAGER_PLUGIN;

        $this->role = apply_filters('cgit_site_manager_role_name', $this->role);
        $this->label = apply_filters('cgit_site_manager_role_label', $this->label);

        if (is_multisite()) {
            $this->userCapabilities['manage_network_users'] = true;
        }

        register_activation_hook($plugin, [$this, 'createRole']);

        $this->addUserActions();
        $this->addThemeActions();
        $this->addPrivacyPolicyActions();
    }

    /**
     * Site manager role can edit users?
     *
     * @return bool
     */
    public function hasUserCapabilities(): bool
    {
        if (!defined('SITE_MANAGER_EDIT_USERS') || SITE_MANAGER_EDIT_USERS) {
            return true;
        }

        return false;
    }

    /**
     * Site manager role can edit theme (navigation)?
     *
     * @return bool
     */
    public function hasThemeCapabilities(): bool
    {
        if (!defined('SITE_MANAGER_EDIT_THEME') || SITE_MANAGER_EDIT_THEME) {
            return true;
        }

        return false;
    }

    /**
     * Site manager role can edit privacy policy?
     *
     * @return bool
     */
    public function hasPrivacyPolicyCapabilities(): bool
    {
        if (!defined('SITE_MANAGER_EDIT_PRIVACY_POLICY') || SITE_MANAGER_EDIT_PRIVACY_POLICY) {
            return true;
        }

        return false;
    }

    /**
     * Create site manager user role
     *
     * @return void
     */
    public function createRole()
    {
        if (!is_multisite()) {
            $this->createRoleCurrentSite();
            return;
        }

        $site_ids = get_sites([
            'fields' => 'ids',
        ]);

        if (!is_array($site_ids) || !$site_ids) {
            return;
        }

        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            $this->createRoleCurrentSite();
            restore_current_blog();
        }
    }

    /**
     * Create site manager user role
     *
     * The new user role should have all the capabilities of the editor role
     * plus the ability to edit users and navigation menus.
     *
     * @return void
     */
    public function createRoleCurrentSite(): void
    {
        $role = apply_filters('cgit_site_manager_base_role', 'editor');
        $caps = get_role($role)->capabilities;

        if ($this->hasUserCapabilities()) {
            $caps = array_merge($caps, $this->userCapabilities);
        }

        if ($this->hasThemeCapabilities()) {
            $caps = array_merge($caps, $this->themeCapabilities);
        }

        // Site manager capabilities can be edited via a filter. This will only
        // take effect when the plugin is reactivated.
        $caps = apply_filters('cgit_site_manager_capabilities', $caps);

        remove_role($this->role);
        add_role($this->role, $this->label, $caps);
    }

    /**
     * Add user-related actions and filters
     *
     * @return void
     */
    private function addUserActions(): void
    {
        if (!$this->hasUserCapabilities()) {
            return;
        }

        add_action('set_user_role', [$this, 'blockDirectUserEdit'], 10, 3);
        add_action('current_screen', [$this, 'blockUserEdit']);
        add_action('user_register', [$this, 'blockUserCreate']);
        add_action('profile_update', [$this, 'blockUserCreate']);
        add_action('delete_user', [$this, 'blockUserDelete']);

        add_filter('editable_roles', [$this, 'blockEditableRoles']);
    }

    /**
     * Add theme-related actions and filters
     *
     * @return void
     */
    private function addThemeActions(): void
    {
        if (!$this->hasThemeCapabilities()) {
            return;
        }

        add_action('current_screen', [$this, 'blockThemeEdit']);
        add_action('admin_menu', [$this, 'hideThemePages']);
    }

    /**
     * Add privacy policy actions and filters
     *
     * @return void
     */
    private function addPrivacyPolicyActions(): void
    {
        if (!$this->hasPrivacyPolicyCapabilities()) {
            return;
        }

        add_filter('user_has_cap', [$this, 'appendPrivacyPolicyCapabilities'], 20, 4);
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
     * Block direct user edit
     *
     * Prevent anyone except administrators from changing the role of an
     * administrator user via the set_role method in the WP_User class. This
     * actually reverts the change after it has been made.
     *
     * @param integer $user_id
     * @param string $new_role
     * @param array $old_roles
     * @return void
     */
    public function blockDirectUserEdit($user_id, $new_role, $old_roles)
    {
        $old_role = array_values($old_roles)[0] ?? null;

        // Current user is an admin user? Previous user role was not an admin
        // role? Permit it.
        if ($this->isAdmin() || $old_role !== $this->adminRole) {
            return;
        }

        // Revert role change
        $user = get_userdata($user_id);
        $user->set_role($old_role);

        // Issue mild rebuke
        $this->nope();
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
     * Block user create
     *
     * Prevent site managers from creating admin users even if they somehow get
     * through the blocked menu on the add-new-user or edit-user screen.
     *
     * @param integer $user_id
     * @return void
     */
    public function blockUserCreate($user_id)
    {
        if (!$this->isSiteManager() || !$this->isAdmin($user_id)) {
            return;
        }

        $user = $this->getUserInstance($user_id);

        $user->remove_role($this->adminRole);
        $user->add_role($this->role);
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

    /**
     * Append privacy policy capabilities for site manager users
     *
     * Because the "manage_options" capability is needed to edit the privacy
     * policy page and because the site manager user role is not intended to
     * have access to the site options, this feature uses a filter on the result
     * of the "user_can" function instead of permanently adding capabilities to
     * the site manager user role.
     *
     * @param array $caps Current user capabilities.
     * @param array $req_caps User capabilities to check.
     * @param array $args Additional user_can function parameters.
     * @param WP_User $wp_user WP_User instance.
     * @return array
     */
    public function appendPrivacyPolicyCapabilities($caps, $req_caps, $args, $wp_user): array
    {
        if (!in_array($this->role, $wp_user->roles)) {
            return $caps;
        }

        $post_id = (int) ($args[2] ?? 0);
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');

        if ($post_id && $privacy_page_id && $post_id === $privacy_page_id) {
            $caps['edit_others_pages'] = true;
            $caps['manage_options'] = true;
        }

        return $caps;
    }
}
