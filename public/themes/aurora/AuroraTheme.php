<?php namespace Themes\Aurora;

use Lib\Theme;
use Lib\ProviderRegistry;

/**
 * Aurora Theme
 *
 * Modern, responsive theme for Pressli CMS with support for posts, pages,
 * and custom content types. Features sidebar widgets, featured posts,
 * and comprehensive category navigation.
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */
class AuroraTheme extends Theme
{
    /**
     * Boot the theme
     *
     * Called when theme is active. Registers theme-specific data providers
     * for template rendering. Providers are registered globally and available
     * to all templates via ProviderRegistry.
     *
     * @return void
     */
    public function boot()
    {
        // Register theme-specific providers
        $this->registerProvider('recent_posts', function($context) {
            return \Models\PostModel::where('status', 'published')
                ->whereNull('deleted_at')
                ->order('created_at', 'desc')
                ->limit(5)
                ->all();
        });

        $this->registerProvider('popular_posts', function($context) {
            return \Models\PostModel::where('status', 'published')
                ->whereNull('deleted_at')
                ->order('views', 'desc')
                ->limit(5)
                ->all();
        });

        $this->registerProvider('featured_posts', function($context) {
            return \Models\PostModel::where('status', 'published')
                ->where('featured', 1)
                ->whereNull('deleted_at')
                ->order('created_at', 'desc')
                ->limit(3)
                ->all();
        });
    }
}
