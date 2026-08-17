<?php namespace Controllers\Admin;

use Rackage\Csrf;
use Rackage\View;
use Rackage\Input;
use Rackage\File;
use Rackage\Upload;
use Rackage\Session;
use Rackage\Redirect;
use Rackage\Registry;
use Lib\PluginManager;
use Models\PluginModel;
use Controllers\Admin\AdminController;

/**
 * Plugins Controller - Pressli CMS
 *
 * Manages plugin installation, activation, deactivation, and deletion.
 * Provides UI for browsing installed plugins and their status.
 *
 * Routes (automatic URL-based routing):
 * - GET  /admin/plugins           List all plugins
 * - POST /admin/plugins/scan      Scan directory for new plugins
 * - POST /admin/plugins/activate/5 Activate plugin by ID
 * - POST /admin/plugins/deactivate/5 Deactivate plugin by ID
 * - POST /admin/plugins/delete/5  Delete plugin by ID
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class PluginsController extends AdminController
{
    /**
     * Display list of all installed plugins
     *
     * Auto-scans plugins directory to sync with database,
     * then displays all plugins with their status and action buttons.
     *
     * @return void
     */
    public function getIndex()
    {
        // Auto-scan to sync plugins directory with database
        PluginManager::scan();

        $plugins = PluginModel::order('name', 'asc')->all();

        // Parse JSON config for each plugin
        foreach ($plugins as &$plugin) {
            if (!empty($plugin['config'])) {
                $plugin['config'] = json_decode($plugin['config'], true);
            }
        }

        $data = [
            'title' => 'Plugins',
            'plugins' => $plugins,
            'settings' => $this->settings
        ];

        View::render('admin/plugins', $data);
    }

    /**
     * Scan plugins directory for new or updated plugins
     *
     * Discovers new plugins, updates version info, marks missing ones inactive.
     * Returns JSON response with scan results.
     *
     * @return void Returns JSON with success/error message
     */
    public function postScan()
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        $results = PluginManager::scan();

        $message = 'Scan complete.';
        if ($results['discovered'] > 0) {
            $message .= ' Discovered ' . $results['discovered'] . ' new plugin(s).';
        }
        if ($results['updated'] > 0) {
            $message .= ' Updated ' . $results['updated'] . ' plugin(s).';
        }
        if ($results['removed'] > 0) {
            $message .= ' Marked ' . $results['removed'] . ' missing plugin(s) as inactive.';
        }

        View::json([
            'success' => true,
            'message' => $message,
            'results' => $results
        ]);
    }

    /**
     * Activate a plugin
     *
     * Loads plugin, runs activate hook, marks as active in database.
     * Plugin boot() will run on subsequent requests.
     *
     * @param int $id Plugin ID from URL
     * @return void Returns JSON with success/error message
     */
    public function postActivate($id)
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        $plugin = PluginModel::where('id', $id)->first();

        if (!$plugin) {
            View::json(['success' => false, 'message' => 'Plugin not found'], 404);
            return;
        }

        if ($plugin['status'] === 'active') {
            View::json(['success' => false, 'message' => 'Plugin is already active'], 400);
            return;
        }

        $success = PluginManager::activate($plugin['slug']);

        if ($success) {
            View::json(['success' => true, 'message' => 'Plugin activated successfully']);
        } else {
            View::json(['success' => false, 'message' => 'Failed to activate plugin'], 500);
        }
    }

    /**
     * Deactivate a plugin
     *
     * Runs plugin deactivate hook, marks as inactive in database.
     * Plugin will not boot on subsequent requests.
     *
     * @param int $id Plugin ID from URL
     * @return void Returns JSON with success/error message
     */
    public function postDeactivate($id)
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        $plugin = PluginModel::where('id', $id)->first();

        if (!$plugin) {
            View::json(['success' => false, 'message' => 'Plugin not found'], 404);
            return;
        }

        if ($plugin['status'] === 'inactive') {
            View::json(['success' => false, 'message' => 'Plugin is already inactive'], 400);
            return;
        }

        $success = PluginManager::deactivate($plugin['slug']);

        if ($success) {
            View::json(['success' => true, 'message' => 'Plugin deactivated successfully']);
        } else {
            View::json(['success' => false, 'message' => 'Failed to deactivate plugin'], 500);
        }
    }

    /**
     * Delete a plugin permanently
     *
     * Deactivates if active, optionally runs uninstall hook to clean data,
     * removes from database, deletes plugin directory.
     *
     * @param int $id Plugin ID from URL
     * @return void Returns JSON with success/error message
     */
    public function postDelete($id)
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        $plugin = PluginModel::where('id', $id)->first();

        if (!$plugin) {
            View::json(['success' => false, 'message' => 'Plugin not found'], 404);
            return;
        }

        $deleteData = Input::post('delete_data') === 'true';

        $success = PluginManager::delete($plugin['slug'], $deleteData);

        if ($success) {
            View::json(['success' => true, 'message' => 'Plugin deleted successfully']);
        } else {
            View::json(['success' => false, 'message' => 'Failed to delete plugin'], 500);
        }
    }

    /**
     * Upload and install a plugin from zip file
     *
     * Validates zip structure, extracts to plugins directory, registers in database.
     * Rolls back on error by deleting extracted files.
     *
     * @return void Returns JSON with success/error message
     */
    public function postUpload()
    {
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        $uploadResult = Upload::file('plugin')
            ->allowedTypes(['zip'])
            ->maxSize(10 * 1024 * 1024)
            ->path('vault/tmp')
            ->save();

        if (!$uploadResult->success) {
            View::json(['success' => false, 'message' => $uploadResult->errorMessage], 400);
            return;
        }

        $zipPath = $uploadResult->fullPath;
        $zip = new \ZipArchive();

        if ($zip->open($zipPath) !== true) {
            unlink($zipPath);
            View::json(['success' => false, 'message' => 'Failed to open zip file'], 500);
            return;
        }

        $tempDir = 'vault/tmp/plugin_' . uniqid();
        File::makeDir($tempDir);

        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            unlink($zipPath);
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Failed to extract zip file'], 500);
            return;
        }

        $zip->close();
        unlink($zipPath);

        $pluginDir = $this->findPluginDirectory($tempDir);
        if (!$pluginDir) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Invalid plugin structure. plugin.json not found'], 400);
            return;
        }

        $configResult = File::readJson($pluginDir . '/plugin.json');
        if (!$configResult->success) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Invalid or missing plugin.json'], 400);
            return;
        }

        $config = $configResult->content;

        if (!isset($config['name']) || !isset($config['slug'])) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'plugin.json missing required fields: name, slug'], 400);
            return;
        }

        $slug = $config['slug'];

        if (PluginModel::where('slug', $slug)->first()) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Plugin already installed: ' . $config['name']], 400);
            return;
        }

        $namespacePart = str_replace('-', '', ucwords($slug, '-'));
        $pluginFile = $pluginDir . '/' . $namespacePart . 'Plugin.php';

        if (!File::exists($pluginFile)->exists) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Plugin class file not found: ' . $namespacePart . 'Plugin.php'], 400);
            return;
        }

        $targetDir = 'plugins/' . $slug;

        if (!File::move($pluginDir, $targetDir)->success) {
            File::deleteDir($tempDir);
            View::json(['success' => false, 'message' => 'Failed to move plugin to plugins directory'], 500);
            return;
        }

        File::deleteDir($tempDir);
        PluginManager::scan();

        View::json([
            'success' => true,
            'message' => 'Plugin installed successfully: ' . $config['name'],
            'plugin' => $config
        ]);
    }

    /**
     * Dispatch GET requests into a plugin's admin panel
     *
     * Prepends the plugin's views directory to view_paths so @extends and
     * @include resolve against the plugin first, falling back to application/views.
     * The plugin receives the HTTP method and URL params and interprets them
     * however it likes — it must return an HTML string.
     *
     * URL: /admin/plugins/manage/{slug}/{p1}/{p2}/{p3}
     *
     * @param string      $slug Plugin slug (e.g. "directory")
     * @param string|null $param1   First URL segment after slug
     * @param string|null $param2   Second URL segment
     * @param string|null $param3   Third URL segment
     * @return void
     */
    public function getManage($slug, $param1 = null, $param2 = null, $param3 = null)
    {
        $namespace = str_replace('-', '', ucwords($slug, '-'));
        $class = 'Plugins\\' . $namespace . '\\' . $namespace . 'Plugin';

        if (!class_exists($class)) {
            View::error(404);
            return;
        }

        $settings = $this->settings;
        $viewPaths = $settings['view_paths'] ?? [];
        $settings['view_paths'] = array_merge(['plugins/' . $namespace . '/templates'], $viewPaths);
        Registry::settings($settings);

        $plugin = new $class();
        $content = $plugin->admin('get', $param1, $param2, $param3);

        View::render('admin/plugins-manage', [
            'title'    => $plugin->getName(),
            'slug'     => $slug,
            'content'  => $content,
            'settings' => $this->settings
        ]);
    }

    /**
     * Dispatch POST requests into a plugin's admin panel
     *
     * Same view_paths prepend as getManage(). The plugin handles its own
     * response for POST (View::json() or Redirect::to()) so nothing is
     * returned or wrapped here.
     *
     * @param string      $slug Plugin slug
     * @param string|null $param1   First URL segment after slug
     * @param string|null $param2   Second URL segment
     * @param string|null $param3   Third URL segment
     * @return void
     */
    public function postManage($slug, $param1 = null, $param2 = null, $param3 = null)
    {
        $namespace = str_replace('-', '', ucwords($slug, '-'));
        $class = 'Plugins\\' . $namespace . '\\' . $namespace . 'Plugin';

        if (!class_exists($class)) {
            View::json(['success' => false, 'message' => 'Plugin not found'], 404);
            return;
        }

        $settings = $this->settings;
        $viewPaths = $settings['view_paths'] ?? [];
        $settings['view_paths'] = array_merge(['plugins/' . $namespace . '/templates'], $viewPaths);
        Registry::settings($settings);

        $plugin = new $class();
        $plugin->admin('post', $param1, $param2, $param3);
    }

    /**
     * Find plugin directory containing plugin.json
     *
     * @param string $dir Directory to search
     * @return string|null Plugin directory path or null
     */
    private function findPluginDirectory($dir)
    {
        if (File::exists($dir . '/plugin.json')->exists) {
            return $dir;
        }

        $dirsResult = File::dirs($dir);
        if ($dirsResult->success) {
            foreach ($dirsResult->files as $subdir) {
                if (File::exists($subdir . '/plugin.json')->exists) {
                    return $subdir;
                }
            }
        }

        return null;
    }
}
