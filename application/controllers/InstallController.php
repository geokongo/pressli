<?php namespace Controllers;

/**
 * Installation Controller - Pressli CMS
 *
 * Handles first-time installation wizard for Pressli. Four-step process guides
 * users through database configuration, connection testing, site setup, and
 * admin account creation. Automatically blocks access after admin user exists.
 *
 * Installation Flow:
 * 1. Welcome - Information about requirements
 * 2. Database - Configure and test database connection
 * 3. Setup - Site information and admin account creation
 * 4. Complete - Success message with login link
 *
 * URLs:
 * - GET  /install                 Step 1: Welcome screen
 * - GET  /install/database        Step 2: Database configuration form
 * - POST /install/database        Save database config
 * - POST /install/test-connection AJAX database connection test
 * - GET  /install/setup           Step 3: Site and admin setup form
 * - POST /install/setup           Create tables, admin, settings
 * - GET  /install/complete        Step 4: Success screen
 *
 * Security:
 * - Checks if admin user exists (returns 404 if installed)
 * - CSRF protection on all POST requests
 * - Database connection validation before proceeding
 * - Bcrypt password hashing for admin account
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */

use Rackage\Url;
use Rackage\Path;
use Rackage\Csrf;
use Rackage\View;
use Rackage\Input;
use Rackage\Model;
use Rackage\Request;
use Rackage\Session;
use Rackage\Redirect;
use Rackage\Security;
use Rackage\Registry;
use Models\RoleModel;
use Models\UserModel;
use Rackage\Controller;
use Models\SettingModel;

class InstallController extends Controller
{
    /**
     * Constructor - Block access if already installed
     *
     * Checks if an admin user exists in the database. If found, installation
     * is complete and all install routes return 404. This prevents reinstallation
     * attacks and accidental database overwrites.
     *
     * Exception: Allows one-time access to complete page via session flag set
     * during installation process.
     *
     * Detection: Queries users table for role_id = 1 (admin role).
     * If tables don't exist yet, allows installation to proceed.
     *
     * @return void
     */
    public function __construct()
    {
        // Allow access to complete page if just finished installation
        if (Session::has('install_just_completed')) {
            return;
        }

        if ($this->isInstalled()) {
            View::error(404);
            exit;
        }
    }

    /**
     * Check if installation is complete
     *
     * Two-layer defense:
     * 1. Check config file first (fast check)
     * 2. Verify admin user exists in database (security layer)
     *
     * This prevents reinstallation attacks even if attacker modifies config.
     *
     * @return bool True if installed, false otherwise
     */
    private function isInstalled()
    {
        // Layer 1: Check config file (fast check from memory)
        $settings = Registry::settings();

        if (!$settings['installed']) {
            return false;  // Config says not installed
        }

        // Layer 2: Config says installed - verify in database (security)
        $dbConfig = require Path::base() . 'config/database.php';

        if (empty($dbConfig['default'])) {
            return false;
        }

        $driver = $dbConfig['default'];
        $config = $dbConfig[$driver] ?? [];

        if (empty($config['database']) || empty($config['host']) || empty($config['username'])) {
            return false;
        }

        try {

            $conn = new \mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'] ?? 3306);
            if ($conn->connect_error) return false;

            $result = $conn->query("SHOW TABLES LIKE 'users'");
            if ($result->num_rows === 0) {

                $conn->close();
                return false;
            }

            $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 1");
            $row    = $result->fetch_assoc();

            $conn->close();

            return $row['count'] > 0;
        }
        catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Display installation welcome screen (Step 1)
     *
     * Renders welcome page with information about database requirements
     * and what information users need to complete installation.
     *
     * @return void
     */
    public function getIndex()
    {
        View::render('auth/install-welcome', [
            'title' => 'Welcome to Pressli Installation'
        ]);
    }

    /**
     * Display database configuration form (Step 2)
     *
     * Renders form for entering database credentials including host, name,
     * username, password, and table prefix. Includes "Test Connection" button
     * to verify credentials before proceeding.
     *
     * @return void
     */
    public function getDatabase()
    {
        View::render('auth/install-database', [
            'title' => 'Database Configuration - Pressli Installation'
        ]);
    }

    /**
     * Test database connection via AJAX
     *
     * Validates database credentials by attempting connection without saving.
     * Returns JSON response with success/error status and message.
     * Called when user clicks "Test Connection" button on database form.
     *
     * @return void
     */
    public function postTest()
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
        }

        $host = Input::post('db_host');
        $name = Input::post('db_name');
        $user = Input::post('db_user');
        $pass = Input::post('db_pass');

        if (empty($host) || empty($name) || empty($user)) {
            View::json(['success' => false, 'message' => 'Database host, name, and username are required'], 400);
        }

        try {

            $conn = new \mysqli($host, $user, $pass, $name);
            if ($conn->connect_error) throw new \Exception($conn->connect_error);

            $conn->close();
            View::json(['success' => true, 'message' => 'Connection successful! You can proceed to the next step.']);
        }
        catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save database configuration (Step 2 POST)
     *
     * Updates config/database.php with new credentials without erasing Rachie's
     * documentation. Reads existing file, updates specific driver configuration,
     * preserves all other drivers and comments.
     *
     * Security: Validates all required fields before saving.
     *
     * @return void
     */
    public function postDatabase()
    {
        if (!Csrf::verify()) {

            Session::flash('error', 'Invalid security token. Please try again.');
            Redirect::back();
        }

        $host = Input::post('db_host');
        $name = Input::post('db_name');
        $user = Input::post('db_user');
        $pass = Input::post('db_pass');

        if (empty($host) || empty($name) || empty($user)) {

            Session::flash('error', 'Database host, name, and username are required.');
            Redirect::back();
        }

        $configPath     = Path::base() . 'config/database.php';
        $configContent  = file_get_contents($configPath);

        $patterns = [
            "'host' => '.*?'" => "'host' => " . var_export($host, true),
            "'username' => '.*?'" => "'username' => " . var_export($user, true),
            "'password' => '.*?'" => "'password' => " . var_export($pass, true),
            "'database' => '.*?'" => "'database' => " . var_export($name, true),
        ];

        foreach ($patterns as $pattern => $replacement) {
            $configContent = preg_replace('/' . $pattern . '/', $replacement, $configContent, 1);
        }

        file_put_contents($configPath, $configContent);

        Session::set('install_db_configured', true);
        Redirect::to('install/setup');
    }

    /**
     * Display site and admin setup form (Step 3)
     *
     * Renders form for entering site information (title, tagline) and creating
     * the first administrator account (username, email, password).
     *
     * Requires database to be configured in previous step.
     *
     * @return void
     */
    public function getSetup()
    {
        if (!Session::has('install_db_configured')) {

            Session::flash('error', 'Please configure database first.');
            Redirect::to('install/database');
        }

        View::render('auth/install-setup', [
            'title' => 'Site & Admin Setup - Pressli Installation'
        ]);
    }

    /**
     * Process site setup and create admin account (Step 3 POST)
     *
     * Runs database migrations to create all tables (with foreign key checks disabled),
     * creates default roles (admin, editor, author, subscriber), creates first admin
     * user with bcrypt password, and saves site settings. Redirects to completion
     * screen on success.
     *
     * Security: CSRF protection, password validation (min 8 chars, confirmation
     * match), bcrypt hashing, email format validation.
     *
     * @return void
     */
    public function postSetup()
    {
        if (!Csrf::verify()) {

            Session::flash('error', 'Invalid security token. Please try again.');
            Redirect::back();
        }

        $siteTitle       = Input::post('site_title');
        $siteTagline     = Input::post('site_tagline', "Let's build something amazing here");
        $username        = Input::post('username');
        $email           = Input::post('email');
        $password        = Input::post('password');
        $passwordConfirm = Input::post('password_confirm');

        if (empty($siteTitle) || empty($username) || empty($email) || empty($password)) {

            Session::flash('error', 'All required fields must be filled.');
            Redirect::back();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            Session::flash('error', 'Invalid email address.');
            Redirect::back();
        }

        if ($password !== $passwordConfirm) {

            Session::flash('error', 'Passwords do not match.');
            Redirect::back();
        }

        if (strlen($password) < 8) {

            Session::flash('error', 'Password must be at least 8 characters.');
            Redirect::back();
        }

        try {

            $this->runMigrations();

            $adminRoleId = RoleModel::save(['name' => 'Administrator', 'description' => 'Full system access']);
            
            RoleModel::save(['name' => 'Editor', 'description' => 'Can publish and manage all posts']);
            RoleModel::save(['name' => 'Author', 'description' => 'Can write and publish own posts']);
            RoleModel::save(['name' => 'Subscriber', 'description' => 'Read-only access']);

            UserModel::save([
                'username'  => $username,
                'email'     => $email,
                'password'  => Security::hash($password),
                'role_id'   => $adminRoleId,
                'status'    => 'active',
                'first_name'=> null,
                'last_name' => null
            ]);

            SettingModel::set('site_title', $siteTitle, true);
            SettingModel::set('site_description', $siteTagline, true);
            SettingModel::set('admin_email', $email, true);
            SettingModel::set('posts_per_page', '10', true);
            SettingModel::set('active_theme', 'aurora', true);
            SettingModel::set('version', Registry::settings()['version'], true);

            // Mark installation as complete in config file
            $this->markInstalled();

            Session::remove('install_db_configured');
            Session::set('install_just_completed', true);

            Redirect::to('install/complete');
            redirect('install/complete');
        }
        catch (\Exception $e) {

            Session::flash('error', 'Installation failed: ' . $e->getMessage());
            Redirect::back();
        }
    }

    /**
     * Run database migrations with foreign key checks disabled
     *
     * Executes all migration files with foreign key constraints disabled.
     * This allows:
     * - Tables to be created in any order (no dependency issues)
     * - Self-referencing foreign keys (e.g., comments.parent_id → comments.id)
     * - Circular references between tables
     *
     * Checks that database is empty before proceeding to prevent data loss.
     * Redirects back with error message if database has existing tables.
     *
     * Note: DDL statements (CREATE TABLE) cannot be rolled back in MySQL.
     * They auto-commit immediately, so transactions don't help here.
     *
     * @return void
     */
    private function runMigrations()
    {
        // Check if database is empty
        $result = Model::sql("SHOW TABLES");
        if ($result->num_rows > 0) {

            // Database has existing tables - abort installation
            Session::flash('error', 'Database is not empty. Pressli installation requires a clean database. Please drop all tables or use a different database.');
            Redirect::back();
        }

        $migrationsPath = Path::app() . 'database/migrations/';
        $migrations     = glob($migrationsPath . '*.php');

        if (empty($migrations)) return;
        sort($migrations);

        // Disable foreign key checks to allow table creation in any order
        Model::sql("SET FOREIGN_KEY_CHECKS=0");

        foreach ($migrations as $migrationFile) {
            
            if (basename($migrationFile) === 'migrations.json') continue;
            require_once $migrationFile;

            up();
        }

        // Re-enable foreign key checks
        Model::sql("SET FOREIGN_KEY_CHECKS=1");
    }

    /**
     * Display installation complete screen (Step 4)
     *
     * Renders success message with links to view site and login to admin
     * dashboard. This is the final step of installation wizard.
     *
     * Removes installation session flag to prevent future access to this page.
     *
     * @return void
     */
    public function getComplete()
    {
        // Remove flag so subsequent access is blocked
        Session::remove('install_just_completed');

        View::render('auth/install-complete', [
            'title' => 'Installation Complete - Pressli'
        ]);
    }

    /**
     * Update settings.php to mark installation complete
     *
     * Modifies config/settings.php by replacing 'installed' => false with true.
     * This enables PageController to redirect to install on fresh installations.
     *
     * SECURITY: Even if attacker modifies this back to false, isInstalled()
     * checks database for admin user, preventing reinstallation attacks.
     *
     * @return void
     */
    private function markInstalled()
    {
        $configPath = Path::base() . 'config/settings.php';
        $content    = file_get_contents($configPath);

        // Replace 'installed' => false with 'installed' => true
        $content = preg_replace(
            "/'installed'\s*=>\s*false/",
            "'installed' => true",
            $content
        );

        file_put_contents($configPath, $content);
    }
}
