<?php namespace Controllers\Admin;

use Rackage\Log;
use Rackage\Csrf;
use Rackage\File;
use Rackage\Path;
use Rackage\View;
use Rackage\Input;
use Rackage\Upload;
use Lib\ThemeConfig;
use Rackage\Session;
use Rackage\Redirect;
use Models\SettingModel;
use Controllers\Admin\AdminController;

/**
 * Themes Controller
 *
 * Manages theme installation, activation, and configuration in the admin panel.
 * Handles theme discovery, metadata loading, and active theme switching.
 *
 * URLs:
 * - GET  /admin/themes        List all available themes
 * - POST /admin/themes/activate/{theme_name}  Activate a theme
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class ThemesController extends AdminController
{
    /**
     * Display all available themes
     *
     * Scans themes directory for valid themes (folders containing theme.json).
     * Loads metadata for each theme and marks the currently active theme.
     * Renders admin themes view with theme cards and activation controls.
     *
     * @return void
     */
    public function index()
    {
        // Get active theme from settings
        $activeThemeName = SettingModel::get('active_theme');

        // Discover all available themes
        $themes = $this->discoverThemes();

        // Load full metadata for each theme
        $themesData = [];
        foreach ($themes as $themeName) {
            try {
                $config = ThemeConfig::load($themeName);
                $meta = $config->getMeta();

                $themesData[] = [
                    'name'          => $themeName,
                    'display_name'  => $meta['name'],
                    'version'       => $meta['version'],
                    'author'        => $meta['author'],
                    'description'   => $meta['description'],
                    'screenshot'    => $this->getThemeScreenshot($themeName),
                    'path'          => $config->getPath(),
                    'is_active'     => ($themeName === $activeThemeName),
                ];
            } 
            catch (\Exception $e) {
                // Skip themes with invalid configuration
                continue;
            }
        }

        // Separate active and available themes
        $activeTheme = null;
        $availableThemes = [];

        foreach ($themesData as $theme) {
            if ($theme['is_active']) $activeTheme = $theme;
            else $availableThemes[] = $theme;
        }

        // Array of data to send to view
        $data = [
            'active_theme' => $activeTheme,
            'available_themes' => $availableThemes,
            'title' => 'Themes',
            'settings' => $this->settings
        ];

        // Render admin themes view
        View::render('admin/themes', $data);
    }

    /**
     * Display add theme page
     *
     * Shows theme upload interface and marketplace directory.
     * Upload section allows manual theme installation via ZIP file.
     * Marketplace shows available themes from directory (currently dummy data).
     *
     * @return void
     */
    public function getAdd()
    {
        $data = [
            'title' => 'Add New Theme',
            'settings' => $this->settings
        ];

        View::render('admin/themes-add', $data);
    }

    /**
     * Redirect GET requests for activation to themes page
     *
     * Handles browser reload after POST activation.
     * Activation is POST-only, so GET requests are redirected.
     *
     * @param string $themeName Theme directory name (unused)
     * @return void
     */
    public function getActivate($themeName = null)
    {
        Redirect::to('admin/themes/index');
    }

    /**
     * Activate a theme
     *
     * Sets the specified theme as active in settings table.
     * Validates theme exists and has valid theme.json before activation.
     * Redirects back to themes page with success/error message.
     *
     * @param string $themeName Theme directory name
     * @return void
     */
    public function postActivate($themeName)
    {
        // Validate theme exists and has valid configuration
        try {
            $config = ThemeConfig::load($themeName);
        }
        catch (\Exception $e) {
            Session::flash('error', 'Cannot activate theme: ' . $e->getMessage());
            Redirect::to('admin/themes/index');
            return;
        }

        // Validate theme has required 'default' page template
        $pageTemplates = $config->getPageTemplates();
        if (!isset($pageTemplates['default'])) {
            Session::flash('error', 'Cannot activate theme: Missing required "default" page template in theme.json');
            Redirect::to('admin/themes/index');
            return;
        }

        // Update active_theme setting in database
        SettingModel::set('active_theme', $themeName, true);

        // Success message with theme name
        $meta = $config->getMeta();
        Session::flash('success', "Theme '{$meta['name']}' has been activated successfully!");

        // Redirect back to themes page
        Redirect::to('admin/themes/index');
    }

    /**
     * Redirect GET requests for deletion to themes page
     *
     * Handles browser reload after POST deletion.
     * Deletion is POST-only, so GET requests are redirected.
     *
     * @param string $themeRoot Theme root directory name (unused)
     * @return void
     */
    public function getDelete($themeRoot = null)
    {
        Redirect::to('admin/themes/index');
    }

    /**
     * Delete a theme
     *
     * Removes theme from both themes/ and public/themes/ directories.
     * Cannot delete active theme (must deactivate first).
     * Cannot delete last remaining theme (at least one must exist).
     *
     * @param string $themeRoot Theme root directory name (PSR-4 namespace segment)
     * @return void
     */
    public function postDelete($themeRoot)
    {
        // Get active theme
        $activeTheme = SettingModel::get('active_theme');

        // Cannot delete active theme
        if ($themeRoot === $activeTheme) {
            Session::flash('error', 'Cannot delete active theme. Please activate a different theme first.');
            Redirect::to('admin/themes/index');
            return;
        }

        // Get all available themes
        $allThemes = $this->discoverThemes();

        // Cannot delete last theme
        if (count($allThemes) <= 1) {
            Session::flash('error', 'Cannot delete the last remaining theme. At least one theme must be installed.');
            Redirect::to('admin/themes/index');
            return;
        }

        // Verify theme exists
        $themeDir = Path::base() . 'themes' . DIRECTORY_SEPARATOR . $themeRoot;
        if (!is_dir($themeDir)) {
            Session::flash('error', 'Theme not found: ' . $themeRoot);
            Redirect::to('admin/themes/index');
            return;
        }

        try {
            // Get theme name for success message
            $themeJsonPath = $themeDir . DIRECTORY_SEPARATOR . 'theme.json';
            $themeName = 'Unknown';
            if (file_exists($themeJsonPath)) {
                $themeConfig = json_decode(file_get_contents($themeJsonPath), true);
                $themeName = $themeConfig['name'] ?? $themeRoot;
            }

            // Delete theme directory
            File::deleteDir($themeDir);

            // Delete public assets directory (lowercase for web portability)
            $publicThemeDir = Path::base() . 'public' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . strtolower($themeRoot);
            if (is_dir($publicThemeDir)) {
                File::deleteDir($publicThemeDir);
            }

            Log::info('Theme deleted', ['root' => $themeRoot, 'name' => $themeName]);
            Session::flash('success', "Theme '{$themeName}' has been deleted successfully!");
        }
        catch (\Exception $e) {
            Log::error('Theme deletion failed', ['root' => $themeRoot, 'error' => $e->getMessage()]);
            Session::flash('error', 'Failed to delete theme: ' . $e->getMessage());
        }

        Redirect::to('admin/themes/index');
    }

    /**
     * Discover all available themes
     *
     * Scans themes/ directory for folders containing valid theme.json files.
     * Returns array of theme directory names that have proper configuration.
     * Excludes folders without theme.json or with invalid JSON structure.
     *
     * @return array Array of theme names ['aurora', 'minimal', 'blog']
     */
    protected function discoverThemes()
    {
        $themesPath = realpath(__DIR__ . '/../../../public/themes');

        if (!$themesPath || !is_dir($themesPath)) {
            return [];
        }

        $themes = [];
        $directories = scandir($themesPath);

        foreach ($directories as $directory) {
            // Skip . and .. and hidden files
            if ($directory[0] === '.') {
                continue;
            }

            $themePath = $themesPath . DIRECTORY_SEPARATOR . $directory;

            // Check if it's a directory
            if (!is_dir($themePath)) {
                continue;
            }

            // Check if theme.json exists
            $configFile = $themePath . DIRECTORY_SEPARATOR . 'theme.json';
            if (!file_exists($configFile)) {
                continue;
            }

            // Validate JSON is parseable
            $json = file_get_contents($configFile);
            $config = json_decode($json, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($config['name'])) {
                $themes[] = $directory;
            }
        }

        return $themes;
    }

    /**
     * Get theme screenshot path
     *
     * Checks for screenshot file in theme directory.
     * Looks for screenshot.png, screenshot.jpg, or screenshot from theme.json.
     * Returns relative path for use in img src attribute, or default placeholder.
     *
     * Priority: theme.json screenshot → screenshot.png → screenshot.jpg → placeholder
     *
     * @param string $themeName Theme directory name
     * @return string Relative path to screenshot image
     */
    protected function getThemeScreenshot($themeName)
    {
        $themesPath = realpath(__DIR__ . '/../../../themes');
        $themePath = $themesPath . DIRECTORY_SEPARATOR . $themeName;
        $assetsPath = $themePath . DIRECTORY_SEPARATOR . 'assets';

        // Check theme.json for screenshot path
        try {
            $config = ThemeConfig::load($themeName);
            $configFile = $themePath . DIRECTORY_SEPARATOR . 'theme.json';
            $json = json_decode(file_get_contents($configFile), true);

            if (isset($json['screenshot'])) {
                $screenshotPath = $assetsPath . DIRECTORY_SEPARATOR . $json['screenshot'];
                if (file_exists($screenshotPath)) {
                    // Public assets copied directly to public/themes/{lowercase}/ without assets subdirectory
                    return '/themes/' . strtolower($themeName) . '/' . $json['screenshot'];
                }
            }
        } 
        catch (\Exception $e) {
            // Continue to default checks
        }

        // Check for common screenshot filenames in assets folder
        $extensions = ['png', 'jpg', 'jpeg', 'gif'];
        foreach ($extensions as $ext) {
            $screenshotPath = $assetsPath . DIRECTORY_SEPARATOR . 'screenshot.' . $ext;
            if (file_exists($screenshotPath)) {
                // Public assets copied directly to public/themes/{lowercase}/ without assets subdirectory
                return '/themes/' . strtolower($themeName) . '/screenshot.' . $ext;
            }
        }

        // Return placeholder if no screenshot found
        return '/admin/images/theme-placeholder.png';
    }

    /**
     * Show theme details page
     *
     * Displays comprehensive theme information including screenshots, features,
     * requirements, templates, and data providers. Loads metadata from theme.json
     * and presents it in a detailed view for evaluation before activation.
     *
     * @param string $themeName Theme directory name
     * @return void
     */
    public function getDetails($themeName)
    {
        // Validate theme exists and load configuration
        try {
            $config = ThemeConfig::load($themeName);
        } 
        catch (\Exception $e) {
            Session::flash('error', 'Theme not found: ' . $themeName);
            Redirect::to('admin/themes/index');
            return;
        }

        // Get theme metadata
        $meta = $config->getMeta();

        // Prepare theme data for detail view
        $themeData = [
            'name' => $themeName,
            'display_name' => $meta['name'],
            'version' => $meta['version'],
            'author' => $meta['author'],
            'description' => $meta['description'],
            'screenshot' => $this->getThemeScreenshot($themeName),
            'settings' => $config->getSettings(),
        ];

        // Check if theme is currently active
        $activeThemeName = SettingModel::get('active_theme');
        $themeData['is_active'] = ($themeName === $activeThemeName);

        // Array of data to send to view
        $data = [
            'theme' => $themeData,
            'title' => $meta['name'] . ' Theme Details',
            'settings' => $this->settings
        ];

        // Render theme details view
        View::render('admin/themes-details', $data);
    }

    /**
     * Show theme customization interface
     *
     * Displays theme customizer with live preview and settings controls.
     * Loads current theme settings and allows modification of colors, fonts,
     * layouts, and other theme-specific options in real-time.
     *
     * @param string $themeName Theme directory name
     * @return void
     */
    public function getCustomize($themeName)
    {
        // Validate theme exists and load configuration
        try {
            $config = ThemeConfig::load($themeName);
        }
        catch (\Exception $e) {
            Session::flash('error', 'Theme not found: ' . $themeName);
            Redirect::to('admin/themes/index');
            return;
        }

        // Get theme metadata
        $meta = $config->getMeta();

        // Load ALL settings from database (site-wide + theme settings)
        $settings = SettingModel::getAutoload(false);

        // Prepare theme data for customizer
        $themeData = [
            'name' => $themeName,
            'display_name' => $meta['name'],
        ];

        // Array of data to send to view
        $data = [
            'theme' => $themeData,
            'settings' => $settings,
            'title' => 'Customize ' . $meta['name'],
            'settings' => $this->settings
        ];

        // Render theme customizer view
        View::render('admin/themes-customize', $data);
    }

    /**
     * Save theme customizer settings via AJAX
     *
     * Saves all customizer settings (site-wide and theme-specific) to database.
     * Uses bulk operations for efficient database writes.
     * Settings are global - applied to active theme.
     * Validates theme is still active before saving (prevents stale tab issues).
     *
     * @param string $themeName Theme directory name from URL
     * @return void JSON response
     */
    public function postCustomize($themeName)
    {
        //file_put_contents(Path::vault() . "logs/error.log",  json_encode(Input::get(), JSON_PRETTY_PRINT)); exit();

        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        // Check if theme is still active (prevent saving to wrong theme if user switched)
        $activeTheme = SettingModel::get('active_theme');
        if (!$activeTheme) {
            View::json(['success' => false, 'message' => 'No active theme set'], 500);
            return;
        }

        if ($themeName !== $activeTheme) {
            View::json([
                'success' => false,
                'message' => 'This theme is no longer active. Please refresh the page.'
            ], 409);
            return;
        }

        // Site-wide settings (autoload=1) - loaded on every page
        $siteSettings = [
            'site_logo' => Input::post('site_logo'),
            'site_favicon' => Input::post('site_favicon'),
            'site_title' => Input::post('site_title'),
            'site_tagline' => Input::post('site_tagline'),
            'show_site_title' => Input::post('show_site_title'),
            'show_tagline' => Input::post('show_tagline'),
            'header_layout' => Input::post('header_layout'),
            'footer_text' => Input::post('footer_text'),
            'show_social_links' => Input::post('show_social_links'),
            'footer_columns' => Input::post('footer_columns'),
            'excerpt_length' => Input::post('excerpt_length'),
            'show_featured_image' => Input::post('show_featured_image'),
            'show_post_date' => Input::post('show_post_date'),
            'show_author' => Input::post('show_author'),
            'show_categories' => Input::post('show_categories'),
            'posts_layout' => Input::post('posts_layout'),
            'sidebar_position' => Input::post('sidebar_position'),
        ];

        // Theme CSS settings (autoload=0) - compiled into CSS file only
        $themeSettings = [
            'primary_color' => Input::post('primary_color'),
            'accent_color' => Input::post('accent_color'),
            'background_color' => Input::post('background_color'),
            'text_color' => Input::post('text_color'),
            'heading_color' => Input::post('heading_color'),
            'body_font' => Input::post('body_font'),
            'heading_font' => Input::post('heading_font'),
            'body_font_size' => Input::post('body_font_size'),
            'content_width' => Input::post('content_width'),
            'footer_background' => Input::post('footer_background'),
        ];

        // Prepare bulk save data for site settings (filter out empty values)
        $siteBulkData = [];
        foreach ($siteSettings as $key => $value) {
            if ($value !== null && $value !== '') {
                $siteBulkData[] = [
                    'name' => $key,
                    'value' => $value,
                    'autoload' => 1
                ];
            }
        }

        // Prepare bulk save data for theme settings (filter out empty values)
        $themeBulkData = [];
        foreach ($themeSettings as $key => $value) {
            if ($value !== null && $value !== '') {
                $themeBulkData[] = [
                    'name' => $key,
                    'value' => $value,
                    'autoload' => 0
                ];
            }
        }

        // Combine all settings
        $allBulkData = array_merge($siteBulkData, $themeBulkData);

        // Bulk upsert all settings (insert if not exists, update if exists)
        if (!empty($allBulkData)) {
            SettingModel::saveUpdate($allBulkData, ['value', 'autoload']);
        }

        // Generate and cache custom CSS (only from saved theme settings)
        $savedThemeSettings = [];
        foreach ($themeSettings as $key => $value) {
            if ($value !== null && $value !== '') {
                $savedThemeSettings[$key] = $value;
            }
        }

        if (!empty($savedThemeSettings)) {
            $this->generateCustomCSS($savedThemeSettings);
        }
        else {
            // No custom settings - delete CSS file
            $cssPath = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme-custom.css';
            if (file_exists($cssPath)) {
                unlink($cssPath);
            }
        }

        View::json(['success' => true, 'message' => 'Settings saved successfully!']);
    }

    /**
     * Generate custom CSS from theme settings and store in file
     *
     * Creates CSS custom properties from theme settings and writes to vault/tmp/theme-custom.css.
     * Output includes <style> tags with ID for client-side replacement in live preview.
     * Only includes properties that have actual values.
     * Persistent across server restarts.
     *
     * IMPORTANT: Theme templates must output this with ID "theme-custom-css" for live preview to work.
     *
     * @param array $settings Theme settings array (already filtered for non-empty values)
     * @return void
     */
    private function generateCustomCSS($settings)
    {
        // Build CSS custom properties with style wrapper
        // ID must match what JavaScript expects for live preview replacement
        $css = '<style id="theme-custom-css">' . "\n";
        $css .= ':root {' . "\n";

        // Colors
        if (isset($settings['primary_color'])) {
            $css .= '    --primary-color: ' . $settings['primary_color'] . ';' . "\n";
        }
        if (isset($settings['accent_color'])) {
            $css .= '    --accent-color: ' . $settings['accent_color'] . ';' . "\n";
        }
        if (isset($settings['background_color'])) {
            $css .= '    --background-color: ' . $settings['background_color'] . ';' . "\n";
        }
        if (isset($settings['text_color'])) {
            $css .= '    --text-color: ' . $settings['text_color'] . ';' . "\n";
        }
        if (isset($settings['heading_color'])) {
            $css .= '    --heading-color: ' . $settings['heading_color'] . ';' . "\n";
        }

        // Typography
        if (isset($settings['body_font'])) {
            $fontFamily = $this->getFontFamily($settings['body_font']);
            if ($fontFamily) {
                $css .= '    --body-font: ' . $fontFamily . ';' . "\n";
            }
        }
        if (isset($settings['heading_font'])) {
            $fontFamily = $this->getFontFamily($settings['heading_font']);
            if ($fontFamily) {
                $css .= '    --heading-font: ' . $fontFamily . ';' . "\n";
            }
        }
        if (isset($settings['body_font_size'])) {
            $css .= '    --body-font-size: ' . $settings['body_font_size'] . 'px;' . "\n";
        }

        // Layout
        if (isset($settings['content_width'])) {
            $contentWidth = $this->getContentWidth($settings['content_width']);
            if ($contentWidth) {
                $css .= '    --content-width: ' . $contentWidth . ';' . "\n";
            }
        }

        // Footer
        if (isset($settings['footer_background'])) {
            $css .= '    --footer-background: ' . $settings['footer_background'] . ';' . "\n";
        }
        if (isset($settings['footer_columns'])) {
            $css .= '    --footer-columns: ' . $settings['footer_columns'] . ';' . "\n";
        }

        $css .= '}' . "\n";
        $css .= '</style>';

        // Write directly to file in vault/tmp/
        $cssPath = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme-custom.css';
        file_put_contents($cssPath, $css);
    }

    /**
     * Get font family CSS value from setting
     *
     * @param string $font Font identifier
     * @return string|null CSS font-family value
     */
    private function getFontFamily($font)
    {
        $fonts = [
            'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'inter' => '"Inter", sans-serif',
            'roboto' => '"Roboto", sans-serif',
            'opensans' => '"Open Sans", sans-serif',
            'lato' => '"Lato", sans-serif',
            'montserrat' => '"Montserrat", sans-serif',
            'playfair' => '"Playfair Display", serif',
            'merriweather' => '"Merriweather", serif',
            'ptserif' => '"PT Serif", serif',
        ];

        return $fonts[$font] ?? null;
    }

    /**
     * Get content width CSS value from setting
     *
     * @param string $width Width identifier
     * @return string|null CSS width value
     */
    private function getContentWidth($width)
    {
        $widths = [
            'narrow' => '800px',
            'medium' => '1000px',
            'wide' => '1200px',
        ];

        return $widths[$width] ?? null;
    }

    /**
     * Reset theme customizer to defaults
     *
     * Deletes all theme-specific settings (autoload=0), keeping site-wide settings intact.
     * Deletes custom CSS file. Template will use hardcoded defaults when settings don't exist.
     *
     * @param string $themeName Theme directory name
     * @return void
     */
    public function getReset($themeName)
    {
        // Delete all theme customization settings (preserve site identity)
        $themeSettings = [
            // CSS settings
            'primary_color', 'accent_color', 'background_color', 'text_color', 'heading_color',
            'body_font', 'heading_font', 'body_font_size', 'content_width', 'footer_background',
            // Layout settings
            'show_site_title', 'show_tagline', 'header_layout', 'footer_text', 'show_social_links',
            'footer_columns', 'excerpt_length', 'show_featured_image', 'show_post_date',
            'show_author', 'show_categories', 'posts_layout', 'sidebar_position',
        ];

        // Delete all theme settings in single query
        SettingModel::whereIn('name', $themeSettings)->delete();

        // Delete custom CSS file
        $cssPath = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme-custom.css';
        if (file_exists($cssPath)) {
            unlink($cssPath);
        }

        Session::flash('success', 'Theme customizations reset to defaults. Site identity (logo, title) preserved.');
        Redirect::to('admin/themes/customize/' . $themeName);
    }

    /**
     * Upload and install a theme from ZIP file
     *
     * Accepts ZIP upload, extracts to temp directory, validates theme structure
     * (theme.json required in root), copies all theme files to themes/{name}/
     * and public assets to public/themes/{name}/. Cleans up temp files after.
     *
     * Validation:
     * - ZIP file required
     * - theme.json must exist in root of ZIP (no subdirectories)
     * - Theme name extracted from theme.json
     * - Duplicate theme names rejected
     *
     * Copy Structure:
     * - themes/{name}/ gets ALL files (complete theme package)
     * - public/themes/{name}/ gets ONLY public/* contents (assets)
     *
     * @return void Returns JSON response
     */
    public function postUpload()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        // Upload ZIP file
        $upload = Upload::file('theme_file')
            ->allowedTypes(['zip'])
            ->maxSize(10 * 1024 * 1024)
            ->path('vault/tmp')
            ->save();

        if (!$upload->success) {
            View::json(['success' => false, 'message' => $upload->errorMessage], 400);
            return;
        }

        $tempDir = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme_' . uniqid();
        File::makeDir($tempDir);

        try {
            // Extract ZIP file
            $zip = new \ZipArchive();
            if ($zip->open($upload->fullPath) !== true) {
                throw new \Exception('Failed to open ZIP file');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Delete uploaded ZIP file
            unlink($upload->fullPath);

            // Check if theme.json exists in root
            $themeJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'theme.json';

            // If not in root, check for single wrapper directory (common ZIP structure)
            if (!file_exists($themeJsonPath)) {
                $contents = array_diff(scandir($tempDir), ['.', '..']);

                // If exactly one item and it's a directory, check inside it
                if (count($contents) === 1) {
                    $subdir = $tempDir . DIRECTORY_SEPARATOR . reset($contents);
                    if (is_dir($subdir)) {
                        $wrappedJsonPath = $subdir . DIRECTORY_SEPARATOR . 'theme.json';
                        if (file_exists($wrappedJsonPath)) {
                            // Unwrap: move everything from subdir to tempDir
                            $files = array_diff(scandir($subdir), ['.', '..']);
                            foreach ($files as $file) {
                                rename(
                                    $subdir . DIRECTORY_SEPARATOR . $file,
                                    $tempDir . DIRECTORY_SEPARATOR . $file
                                );
                            }
                            rmdir($subdir);
                            $themeJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'theme.json';
                        }
                    }
                }
            }

            // Final validation: theme.json must exist
            if (!file_exists($themeJsonPath)) {
                throw new \Exception('Invalid theme: theme.json not found in ZIP root or single wrapper directory');
            }

            // Load and validate theme.json
            $themeConfig = json_decode(file_get_contents($themeJsonPath), true);
            if (!$themeConfig || !isset($themeConfig['name'])) {
                throw new \Exception('Invalid theme.json: missing "name" field');
            }

            // Validate root field exists (PSR-4 namespace segment)
            if (!isset($themeConfig['root'])) {
                throw new \Exception('Invalid theme.json: missing "root" field (theme directory name for PSR-4 autoloading)');
            }

            $root = $themeConfig['root'];

            // Validate root is PSR-4 compatible (PascalCase)
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $root)) {
                throw new \Exception('Invalid root: must be PascalCase (e.g., "Bota", "MinimalTheme") for PSR-4 autoloading');
            }

            // Check if theme already exists
            $themeDir = Path::base() . 'themes' . DIRECTORY_SEPARATOR . $root;
            if (file_exists($themeDir)) {
                throw new \Exception('Theme already exists: ' . $root);
            }

            // Create theme directories
            // Public assets use lowercase for web portability (case-sensitive Linux Apache)
            $publicThemeDir = Path::base() . 'public' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . strtolower($root);
            File::makeDir($themeDir);
            File::makeDir($publicThemeDir);

            // Copy ALL theme files to themes/{root}/
            File::copyDir($tempDir, $themeDir);

            // Copy assets CONTENTS directly to public/themes/{lowercase-root}/
            // Result: themes/Bota/assets/css/ → public/themes/bota/css/ (lowercase for web URLs)
            $sourceAssetsDir = $tempDir . DIRECTORY_SEPARATOR . 'assets';
            if (is_dir($sourceAssetsDir)) {
                File::copyDir($sourceAssetsDir, $publicThemeDir);
            }

            // Clean up temp directory
            File::deleteDir($tempDir);

            Log::info('Theme installed successfully', ['root' => $root, 'name' => $themeConfig['name']]);

            View::json([
                'success' => true,
                'message' => 'Theme installed successfully: ' . $themeConfig['name']
            ]);
        }
        catch (\Exception $e) {
            // Clean up on error
            if (isset($tempDir) && is_dir($tempDir)) {
                File::deleteDir($tempDir);
            }
            if (isset($themeDir) && is_dir($themeDir)) {
                File::deleteDir($themeDir);
            }
            if (isset($publicThemeDir) && is_dir($publicThemeDir)) {
                File::deleteDir($publicThemeDir);
            }

            Log::error('Theme installation failed', ['error' => $e->getMessage()]);

            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing theme
     *
     * Uploads new theme ZIP, validates it matches the existing theme (same root),
     * backs up current version, and overwrites with new files.
     * Used for theme updates from theme details page.
     *
     * @param string $themeRoot Theme root directory name to update
     * @return void Returns JSON response
     */
    public function postUpdate($themeRoot)
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        // Verify theme exists
        $themeDir = Path::base() . 'themes' . DIRECTORY_SEPARATOR . $themeRoot;
        if (!is_dir($themeDir)) {
            View::json(['success' => false, 'message' => 'Theme not found: ' . $themeRoot], 404);
            return;
        }

        // Get current version for comparison
        $currentThemeJson = $themeDir . DIRECTORY_SEPARATOR . 'theme.json';
        $currentConfig = json_decode(file_get_contents($currentThemeJson), true);
        $currentVersion = $currentConfig['version'] ?? 'unknown';

        // Upload ZIP file
        $upload = Upload::file('theme_file')
            ->allowedTypes(['zip'])
            ->maxSize(10 * 1024 * 1024)
            ->path('vault/tmp')
            ->save();

        if (!$upload->success) {
            View::json(['success' => false, 'message' => $upload->errorMessage], 400);
            return;
        }

        $tempDir = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme_update_' . uniqid();
        File::makeDir($tempDir);

        try {
            // Extract ZIP file
            $zip = new \ZipArchive();
            if ($zip->open($upload->fullPath) !== true) {
                throw new \Exception('Failed to open ZIP file');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Delete uploaded ZIP file
            unlink($upload->fullPath);

            // Check if theme.json exists in root or unwrap single directory
            $themeJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'theme.json';

            if (!file_exists($themeJsonPath)) {
                $contents = array_diff(scandir($tempDir), ['.', '..']);

                if (count($contents) === 1) {
                    $subdir = $tempDir . DIRECTORY_SEPARATOR . reset($contents);
                    if (is_dir($subdir)) {
                        $wrappedJsonPath = $subdir . DIRECTORY_SEPARATOR . 'theme.json';
                        if (file_exists($wrappedJsonPath)) {
                            $files = array_diff(scandir($subdir), ['.', '..']);
                            foreach ($files as $file) {
                                rename(
                                    $subdir . DIRECTORY_SEPARATOR . $file,
                                    $tempDir . DIRECTORY_SEPARATOR . $file
                                );
                            }
                            rmdir($subdir);
                            $themeJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'theme.json';
                        }
                    }
                }
            }

            if (!file_exists($themeJsonPath)) {
                throw new \Exception('Invalid theme: theme.json not found in ZIP root or single wrapper directory');
            }

            // Load and validate new theme.json
            $newConfig = json_decode(file_get_contents($themeJsonPath), true);
            if (!$newConfig || !isset($newConfig['name'])) {
                throw new \Exception('Invalid theme.json: missing "name" field');
            }

            if (!isset($newConfig['root'])) {
                throw new \Exception('Invalid theme.json: missing "root" field');
            }

            $newRoot = $newConfig['root'];

            // Validate root matches (can't change theme identity)
            if ($newRoot !== $themeRoot) {
                throw new \Exception("Theme mismatch: Update is for '{$newRoot}' but trying to update '{$themeRoot}'");
            }

            // Validate PSR-4 format
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $newRoot)) {
                throw new \Exception('Invalid root: must be PascalCase for PSR-4 autoloading');
            }

            $newVersion = $newConfig['version'] ?? 'unknown';

            // Backup current theme
            $backupDir = Path::vault() . 'backups' . DIRECTORY_SEPARATOR . 'themes';
            File::makeDir($backupDir);
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $themeRoot . '-' . date('Y-m-d-His');
            File::copyDir($themeDir, $backupPath);

            // Also backup public assets (lowercase directory for web portability)
            $publicThemeDir = Path::base() . 'public' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . strtolower($themeRoot);
            if (is_dir($publicThemeDir)) {
                $publicBackupPath = $backupPath . '-public';
                File::copyDir($publicThemeDir, $publicBackupPath);
            }

            // Delete current theme files (but keep directory structure)
            $files = array_diff(scandir($themeDir), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $themeDir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    File::deleteDir($filePath);
                }
                else {
                    unlink($filePath);
                }
            }

            // Copy new theme files
            File::copyDir($tempDir, $themeDir);

            // Update public assets
            if (is_dir($publicThemeDir)) {
                File::deleteDir($publicThemeDir);
            }
            File::makeDir($publicThemeDir);

            // Copy assets CONTENTS directly to public/themes/{lowercase-root}/
            // Result: themes/Aurora/assets/css/ → public/themes/aurora/css/ (lowercase for web URLs)
            $sourceAssetsDir = $tempDir . DIRECTORY_SEPARATOR . 'assets';
            if (is_dir($sourceAssetsDir)) {
                File::copyDir($sourceAssetsDir, $publicThemeDir);
            }

            // Clean up temp directory
            File::deleteDir($tempDir);

            Log::info('Theme updated successfully', [
                'root' => $themeRoot,
                'name' => $newConfig['name'],
                'old_version' => $currentVersion,
                'new_version' => $newVersion,
                'backup' => $backupPath
            ]);

            View::json([
                'success' => true,
                'message' => "Theme updated successfully from v{$currentVersion} to v{$newVersion}",
                'old_version' => $currentVersion,
                'new_version' => $newVersion
            ]);
        }
        catch (\Exception $e) {
            // Clean up on error
            if (isset($tempDir) && is_dir($tempDir)) {
                File::deleteDir($tempDir);
            }

            Log::error('Theme update failed', ['root' => $themeRoot, 'error' => $e->getMessage()]);

            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
