<?php namespace Controllers\Admin;

use Rackage\View;
use Rackage\Csrf;
use Rackage\Url;
use Rackage\Path;
use Rackage\Input;
use Rackage\Session;
use Rackage\Redirect;
use Rackage\Request;
use Rackage\Upload;
use Rackage\File;
use Models\PostModel;
use Models\SettingModel;
use Models\MediaModel;
use Models\TaxonomyModel;
use Controllers\Admin\AdminController;

/**
 * Admin Tools Controller
 *
 * Handles advanced administrative tools including:
 * - Search & Replace URLs (site migration)
 * - Pressli Core Updates (upload and install)
 * - Database maintenance tools
 *
 * @package Controllers\Admin
 */
class ToolsController extends AdminController
{
    /**
     * Display tools dashboard
     *
     * Shows available administrative tools with current system info.
     *
     * @return void
     */
    public function getIndex()
    {
        // Check the latest version
        $manifest   = file_get_contents('https://pressli.org/downloads/manifest.json');
        $manifest   = json_decode($manifest, true);

        // Ignore if you have the latest version
        $oldVersion  = $this->settings['version'];
        $newVersion  = $manifest['latest_version'];

        // Get system info
        $systemInfo = [
            'php_version'       => phpversion(),
            'mysql_version'     => $this->getMySQLVersion(),
            'pressli_version'   => $oldVersion,
            'site_url'          => $this->settings['site_url'] ?? '',
            'upload_max_size'   => ini_get('upload_max_filesize'),
            'post_max_size'     => ini_get('post_max_size'),
        ];

        // Compare version numbers with PHP native version_compare():
        //  -1 if the first version is lower
        //   0 if they are equal
        //   1 if the second version is lower
        if (version_compare($newVersion, $oldVersion, '>')) {           
           $systemInfo['new_version'] = $newVersion;
        }

        // Array of data to send to view
        $data = [
            'title'         => 'Tools',
            'systemInfo'    => $systemInfo,
            'settings'      => $this->settings
        ];

        View::render('admin/tools', $data);
    }

    /**
     * Generate sitemap.xml
     *
     * Creates XML sitemap with all published posts, pages, categories, and tags.
     * Saves to public/sitemap.xml
     *
     * @return void
     */
    public function postSitemap()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        try {
            $baseUrl = Url::base();

            // Start XML
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            $urlCount = 0;

            // Homepage
            $xml .= "\n  <!-- Homepage -->\n";
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . rtrim($baseUrl, '/') . '/</loc>' . "\n";
            $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '  </url>' . "\n";
            $urlCount++;

            // Add published pages
            $pages = PostModel::where('type', 'page')
                ->where('status', 'published')
                ->select(['slug', 'updated_at'])
                ->all();

            if (!empty($pages)) {
                $xml .= "\n  <!-- Pages -->\n";
                foreach ($pages as $page) {
                    $xml .= '  <url>' . "\n";
                    $xml .= '    <loc>' . rtrim($baseUrl, '/') . '/' . $page['slug'] . '</loc>' . "\n";
                    $xml .= '    <lastmod>' . date('Y-m-d', strtotime($page['updated_at'])) . '</lastmod>' . "\n";
                    $xml .= '  </url>' . "\n";
                    $urlCount++;
                }
            }

            // Add published posts
            $posts = PostModel::where('type', 'post')
                ->where('status', 'published')
                ->select(['slug', 'updated_at'])
                ->all();

            if (!empty($posts)) {
                $xml .= "\n  <!-- Posts -->\n";
                foreach ($posts as $post) {
                    $xml .= '  <url>' . "\n";
                    $xml .= '    <loc>' . rtrim($baseUrl, '/') . '/' . $post['slug'] . '</loc>' . "\n";
                    $xml .= '    <lastmod>' . date('Y-m-d', strtotime($post['updated_at'])) . '</lastmod>' . "\n";
                    $xml .= '  </url>' . "\n";
                    $urlCount++;
                }
            }

            // Add categories
            $categories = TaxonomyModel::where('type', 'category')->all();
            if (!empty($categories)) {
                $xml .= "\n  <!-- Categories -->\n";
                foreach ($categories as $category) {
                    $xml .= '  <url>' . "\n";
                    $xml .= '    <loc>' . rtrim($baseUrl, '/') . '/category/' . $category['slug'] . '</loc>' . "\n";
                    $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
                    $xml .= '  </url>' . "\n";
                    $urlCount++;
                }
            }

            // Add tags
            $tags = TaxonomyModel::where('type', 'tag')->all();
            if (!empty($tags)) {
                $xml .= "\n  <!-- Tags -->\n";
                foreach ($tags as $tag) {
                    $xml .= '  <url>' . "\n";
                    $xml .= '    <loc>' . rtrim($baseUrl, '/') . '/tag/' . $tag['slug'] . '</loc>' . "\n";
                    $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
                    $xml .= '  </url>' . "\n";
                    $urlCount++;
                }
            }

            // Close XML
            $xml .= '</urlset>';

            // Full file path
            $sitemap = Path::base() . 'public/sitemap.xml';

            // Save to public/sitemap.xml
            File::write($sitemap, $xml);

            View::json([
                'success' => true,
                'message' => "Sitemap generated successfully! {$urlCount} URLs included.",
                'sitemapUrl' => Url::base() . 'sitemap.xml'
            ]);
        }
        catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Sitemap generation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate robots.txt file
     *
     * Creates robots.txt file with sitemap reference and basic crawl rules.
     *
     * @return void
     */
    public function postRobots()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        try {
            $baseUrl = Url::base();

            // Build robots.txt content
            $content = "# Robots.txt for " . SettingModel::where('name', 'site_title')->first()['value'] . "\n\n";
            $content .= "User-agent: *\n";
            $content .= "Allow: /\n\n";
            $content .= "# Disallow admin areas\n";
            $content .= "Disallow: /admin/\n";
            $content .= "Disallow: /vault/\n\n";
            $content .= "# Sitemap\n";
            $content .= "Sitemap: " . rtrim($baseUrl, '/') . "/sitemap.xml\n";

            // Save to public/robots.txt
            $robotsFile = Path::base() . 'public/robots.txt';
            File::write($robotsFile, $content);

            View::json([
                'success' => true,
                'message' => 'Robots.txt generated successfully!',
                'robotsUrl' => Url::base() . 'robots.txt'
            ]);
        }
        catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Robots.txt generation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Search for URL occurrences (preview)
     *
     * Scans database and theme files for URL occurrences without making changes.
     * Returns JSON with counts of affected records.
     *
     * @return void
     */
    public function postSearch()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        $oldUrl = $input['oldUrl'] ?? '';
        $newUrl = $input['newUrl'] ?? '';
        $includeThemes = $input['includeThemes'] ?? false;

        // Validate inputs
        if (empty($oldUrl) || empty($newUrl)) {
            View::json(['success' => false, 'message' => 'Both URLs are required'], 400);
            return;
        }

        if ($oldUrl === $newUrl) {
            View::json(['success' => false, 'message' => 'URLs cannot be the same'], 400);
            return;
        }

        // Search database
        $databaseResults = $this->searchDatabase($oldUrl);

        // Search theme files if requested
        $themeResults = ['total' => 0, 'files' => []];
        if ($includeThemes) {
            $themeResults = $this->searchThemeFiles($oldUrl);
        }

        View::json([
            'success' => true,
            'database' => $databaseResults,
            'theme' => $themeResults
        ]);
    }

    /**
     * Execute URL replacement
     *
     * Performs actual URL replacement in database and theme files.
     * Returns JSON with total replacements made.
     *
     * @return void
     */
    public function postReplace()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::json(['success' => false, 'message' => 'Invalid security token'], 403);
            return;
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        $oldUrl = $input['oldUrl'] ?? '';
        $newUrl = $input['newUrl'] ?? '';
        $includeThemes = $input['includeThemes'] ?? false;

        // Validate inputs
        if (empty($oldUrl) || empty($newUrl)) {
            View::json(['success' => false, 'message' => 'Both URLs are required'], 400);
            return;
        }

        try {
            // Replace in database
            $databaseCount = $this->replaceInDatabase($oldUrl, $newUrl);

            // Replace in theme files if requested
            $themeCount = 0;
            if ($includeThemes) {
                $themeCount = $this->replaceInThemeFiles($oldUrl, $newUrl);
            }

            $totalReplacements = $databaseCount + $themeCount;

            View::json([
                'success' => true,
                'totalReplacements' => $totalReplacements,
                'databaseReplacements' => $databaseCount,
                'themeReplacements' => $themeCount
            ]);

        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Pressli core update
     * 
     * Polls pressli.org for the latest version, and if there's a new version of
     * Pressli, it downloads and updates the current version.
     *
     * @return void
     */
    public function getUpdate()
    {
        // Check the latest version
        $manifest   = file_get_contents('https://pressli.org/downloads/manifest.json');
        $manifest   = json_decode($manifest, true);

        // Ignore if you have the latest version
        $oldVersion  = $this->settings['version'];
        $newVersion  = $manifest['latest_version'];

        // Compare version numbers with PHP native version_compare():
        //  -1 if the first version is lower
        //   0 if they are equal
        //   1 if the second version is lower
        if (!version_compare($newVersion, $oldVersion, '>')) {
           
            Session::flash('success', 'Pressli is already on the latest version');
            Redirect::to('admin/tools');
        }

        // Download the new version of the Pressli
        $newRelease  = Path::base("vault/tmp/pressli-$newVersion.zip");

        $handle  = curl_init($manifest['download_url']);
        $pointer = fopen($newRelease, 'wb+');

        // Set curl opt options
        curl_setopt($handle, CURLOPT_FILE, $pointer);
        curl_setopt($handle, CURLOPT_HEADER, 0);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true); // Crucial: GitHub redirects downloads to AWS S3
        curl_setopt($handle, CURLOPT_USERAGENT, 'Pressli CMS'); // GitHub API requires a User-Agent header
        
        // Ensure downloads only happen over https
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Download the file
        curl_exec($handle);
        
        // Close both file and curl handles
        curl_close($handle);
        fclose($pointer);   
        
        // New Release Checksum
        $newChecksum = hash_file('sha256', $newRelease);

        // Reject if checksum do not match
        if ($newChecksum !== $manifest['checksum']) {
            
            // Delete the bad file immediately
            unlink($newRelease);

            Session::flash('error', 'CMS Update Failed: Checksum mismatch. The file may be corrupted or malicious.');
            Redirect::to('admin/tools');            
        }

        // Proceed to update Pressli


        try {

            $newPressli    = new \ZipArchive();
            if($newPressli->open($newRelease) == true){

                // Loop through each file copying the contents over
                for($i = 0; $i < $newPressli->numFiles; $i++) {

                    $filePath   = $newPressli->getNameIndex($i);
                    $newPath    = Path::base($filePath);

                    // Create directory if it doens't exit
                    if(str_ends_with($filePath, "/")) {
                        
                        if(!is_dir($newPath)) {
                            // Create a writable folder
                            mkdir($newPath, 0755, true);
                        }

                        chmod($newPath, 0755);
                        continue;
                    }

                    // Skip config
                    if(str_starts_with($filePath, "config/")) continue;

                    // Parent directory exists before writing file
                    $dirname    = dirname($newPath);
                    if(!is_dir($dirname)) {
                        mkdir($dirname, 0755, true);
                    }

                    // Extract and copy new file contents
                    file_put_contents($newPath, $newPressli->getFromIndex($i));                   
                }

                // Update CMS version number
                SettingModel::set('version', $newVersion, true);

                $newPressli->close(); 
                @unlink($newRelease); 
            }
            else {

                @unlink($newRelease);
                Session::flash('error', "Failed to open $newRelease");
                Redirect::to('admin/tools');
            }                      

            Session::flash('success', 'Pressli updated successfully!');
            Redirect::to('admin/tools');

        } 
        catch (\Throwable $exception) {

            Session::flash('error', 'Update failed: ' . $exception->getMessage());
            Redirect::to('admin/tools');
        }
    }

    /**
     * Handle Pressli core update upload
     *
     * Accepts zip file upload, validates, extracts, and updates core files.
     * Creates backup before updating.
     *
     * @return void
     */
    public function postUpdate()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            Session::flash('error', 'Invalid security token');
            Redirect::to('admin/tools');
            return;
        }

        // Handle file upload
        $upload = Upload::file('update_file')
            ->allowedTypes(['zip'])
            ->maxSize(50 * 1024 * 1024) // 50MB max
            ->path('vault/tmp')
            ->save();

        if (!$upload->success) {
            Session::flash('error', 'Upload failed: ' . $upload->errorMessage);
            Redirect::to('admin/tools');
            return;
        }

        try {
            // Extract zip file
            $zipPath = $upload->fullPath;
            $extractPath = 'vault/tmp/pressli-update-' . time();

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception('Failed to open zip file');
            }

            // Create extraction directory
            File::makeDir($extractPath);

            // Extract
            $zip->extractTo($extractPath);
            $zip->close();

            // Verify extracted structure (should have application/, public/, etc.)
            if (!File::isDir($extractPath . '/application')->exists) {
                throw new \Exception('Invalid update package structure');
            }

            // Create backup of current installation
            $backupPath = $this->createBackup();

            // Copy files from extracted update to root (excluding config and vault)
            $this->updateFiles($extractPath);

            // Clean up
            File::delete($zipPath);
            File::deleteDir($extractPath);

            Session::flash('success', 'Pressli updated successfully! Backup created at: ' . $backupPath);
            Redirect::to('admin/tools');

        } catch (\Exception $e) {
            Session::flash('error', 'Update failed: ' . $e->getMessage());
            Redirect::to('admin/tools');
        }
    }

    /**
     * Search database for URL occurrences
     *
     * @param string $url URL to search for
     * @return array Counts by table/field
     */
    private function searchDatabase($url)
    {
        $counts = [
            'posts_content' => 0,
            'posts_excerpt' => 0,
            'settings' => 0,
            'media' => 0,
            'total' => 0
        ];

        // Search posts content
        $counts['posts_content'] = PostModel::whereLike('content', '%' . $url . '%')->count();

        // Search posts excerpt
        $counts['posts_excerpt'] = PostModel::whereLike('excerpt', '%' . $url . '%')->count();

        // Search settings values
        $counts['settings'] = SettingModel::whereLike('value', '%' . $url . '%')->count();

        // Search media file paths
        $counts['media'] = MediaModel::whereLike('file_path', '%' . $url . '%')->count();

        $counts['total'] = $counts['posts_content'] + $counts['posts_excerpt'] +
                          $counts['settings'] + $counts['media'];

        return $counts;
    }

    /**
     * Search theme files for URL occurrences
     *
     * @param string $url URL to search for
     * @return array Total count and affected files
     */
    private function searchThemeFiles($url)
    {
        $activeTheme = $this->settings['active_theme'] ?? 'aurora';
        $themePath = 'themes/' . $activeTheme;

        $affectedFiles = [];
        $totalOccurrences = 0;

        if (!File::isDir($themePath)->exists) {
            return ['total' => 0, 'files' => []];
        }

        // Get all PHP files in theme
        $files = File::glob($themePath . '/**/*.php')->files ?? [];

        foreach ($files as $file) {
            $content = File::read($file)->content ?? '';
            $count = substr_count($content, $url);

            if ($count > 0) {
                $affectedFiles[] = str_replace($themePath . '/', '', $file) . " ({$count})";
                $totalOccurrences += $count;
            }
        }

        return [
            'total' => $totalOccurrences,
            'files' => $affectedFiles
        ];
    }

    /**
     * Replace URL in database
     *
     * @param string $oldUrl URL to replace
     * @param string $newUrl Replacement URL
     * @return int Total replacements made
     */
    private function replaceInDatabase($oldUrl, $newUrl)
    {
        $totalReplacements = 0;

        // Replace in posts content
        $posts = PostModel::whereLike('content', '%' . $oldUrl . '%')->all();
        foreach ($posts as $post) {
            $newContent = str_replace($oldUrl, $newUrl, $post['content']);
            PostModel::where('id', $post['id'])->save(['content' => $newContent]);
            $totalReplacements++;
        }

        // Replace in posts excerpt
        $posts = PostModel::whereLike('excerpt', '%' . $oldUrl . '%')->all();
        foreach ($posts as $post) {
            $newExcerpt = str_replace($oldUrl, $newUrl, $post['excerpt']);
            PostModel::where('id', $post['id'])->save(['excerpt' => $newExcerpt]);
            $totalReplacements++;
        }

        // Replace in settings
        $settings = SettingModel::whereLike('value', '%' . $oldUrl . '%')->all();
        foreach ($settings as $setting) {
            $newValue = str_replace($oldUrl, $newUrl, $setting['value']);
            SettingModel::where('id', $setting['id'])->save(['value' => $newValue]);
            $totalReplacements++;
        }

        // Replace in media file paths
        $media = MediaModel::whereLike('file_path', '%' . $oldUrl . '%')->all();
        foreach ($media as $item) {
            $newPath = str_replace($oldUrl, $newUrl, $item['file_path']);
            MediaModel::where('id', $item['id'])->save(['file_path' => $newPath]);
            $totalReplacements++;
        }

        return $totalReplacements;
    }

    /**
     * Replace URL in theme files
     *
     * @param string $oldUrl URL to replace
     * @param string $newUrl Replacement URL
     * @return int Total files modified
     */
    private function replaceInThemeFiles($oldUrl, $newUrl)
    {
        $activeTheme = $this->settings['active_theme'] ?? 'aurora';
        $themePath = 'themes/' . $activeTheme;

        $filesModified = 0;

        if (!File::isDir($themePath)->exists) {
            return 0;
        }

        // Get all PHP files in theme
        $files = File::glob($themePath . '/**/*.php')->files ?? [];

        foreach ($files as $file) {
            $content = File::read($file)->content ?? '';

            if (strpos($content, $oldUrl) !== false) {
                $newContent = str_replace($oldUrl, $newUrl, $content);
                File::write($file, $newContent);
                $filesModified++;
            }
        }

        return $filesModified;
    }

    /**
     * Create backup of current installation
     *
     * @return string Backup directory path
     */
    private function createBackup()
    {
        $backupDir = 'vault/backups/pressli-' . date('Y-m-d-His');

        File::makeDir($backupDir);

        // Copy critical directories (excluding vault to avoid recursion)
        $dirsToBackup = ['application', 'public', 'config', 'themes'];

        foreach ($dirsToBackup as $dir) {
            if (File::isDir($dir)->exists) {
                $this->copyDirectory($dir, $backupDir . '/' . $dir);
            }
        }

        return $backupDir;
    }

    /**
     * Update files from extracted package
     *
     * @param string $sourcePath Path to extracted update files
     * @return void
     */
    private function updateFiles($sourcePath)
    {
        $dirsToUpdate = ['application', 'public', 'vendor'];

        foreach ($dirsToUpdate as $dir) {
            $source = $sourcePath . '/' . $dir;

            if (File::isDir($source)->exists) {
                // Delete existing directory
                if (File::isDir($dir)->exists) {
                    File::deleteDir($dir);
                }

                // Copy new files
                $this->copyDirectory($source, $dir);
            }
        }
    }

    /**
     * Recursively copy directory
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return void
     */
    private function copyDirectory($source, $destination)
    {
        File::makeDir($destination);

        $files = File::allFiles($source)->files ?? [];

        foreach ($files as $file) {
            $relativePath = str_replace($source . '/', '', $file);
            $destFile = $destination . '/' . $relativePath;

            // Create parent directory if needed
            $destDir = dirname($destFile);
            if (!File::isDir($destDir)->exists) {
                File::makeDir($destDir);
            }

            // Copy file
            File::copy($file, $destFile);
        }
    }

    /**
     * Get MySQL version
     *
     * @return string MySQL version number
     */
    private function getMySQLVersion()
    {
        try {
            $result = PostModel::sql("SELECT VERSION() as version");
            $row = $result->fetch_assoc();
            return $row['version'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
