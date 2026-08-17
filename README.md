# Pressli CMS

I built Pressli on the Rachie PHP framework to give you a complete CMS without the bloat. You can use it for blogs, marketing sites, or any content-driven website.

Here's what you get:
- A complete admin dashboard for managing your content
- Role-based permissions for your team
- Plugin and theme systems you can extend
- RESTful API if you want to go headless
- Service layer architecture that keeps your business logic clean

**Version:** 1.0.0
**Website:** https://pressli.org
**License:** MIT

---

## Features

### Content Management
You can create and manage blog posts with categories, tags, and featured images. Build static pages with parent-child hierarchies for your site structure. The built-in comment system lets you moderate discussions on your posts.

The media library handles all your uploads - images, documents, whatever you need. You can organize files, edit metadata, and insert them into your content.

### Site Management
Your navigation menus are drag-and-drop. Create multiple menus for different locations (header, footer, sidebar).

User management includes four roles out of the box: Admin, Editor, Author, and Subscriber. Each role has granular permissions you can control.

Themes separate your content from presentation. Switch themes without touching your content. The theme customizer lets you adjust colors, fonts, and layouts from the admin panel.

### Developer Features
The annotation-driven ORM means you define your database schema in model docblocks. Change a model, run a migration - Pressli handles the rest.

The service layer (Post, Page, Comment, Media, Menu, Taxonomy) gives you reusable business logic. Call these services from controllers, API endpoints, or CLI commands.

Provider Registry loads data on-demand. Your themes declare what data they need, Pressli fetches only that. No wasted queries.

Content Registry lets plugins register custom post types. Build a job board, directory, events calendar - whatever your project needs.

Roline CLI gives you commands for scaffolding models, controllers, migrations, and database operations.

---

## Requirements

Before you install Pressli, make sure your server has:

- **PHP 8.0 or higher** - We use modern PHP features
- **MySQL 5.7+** or **MariaDB 10.2+** - For your database
- **Apache with mod_rewrite** - For clean URLs
- **Composer** - To install dependencies

Your hosting should let you create databases and set file permissions. Most shared hosting and VPS providers support these requirements.

---

## Installation

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/glivers/pressli.git
   cd pressli
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up your database**

   Create a MySQL database for Pressli. You'll need:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

4. **Run the installation wizard**

   Point your browser to your Pressli installation:
   ```
   http://yourdomain.com/install
   ```

   The wizard walks you through:
   - Database connection setup
   - Admin account creation
   - Basic site settings

   Follow the on-screen instructions. The wizard tests your database connection before proceeding.

5. **Access your admin panel**

   After installation completes, go to:
   ```
   http://yourdomain.com/admin
   ```

   Log in with the credentials you created during installation.

### File Permissions

Make sure these directories are writable by your web server:

```bash
chmod -R 755 vault/
chmod -R 755 public/uploads/
```

On some servers, you might need `775` or `777`. Use the most restrictive permission that works.

---

## Usage

### Creating Your First Post

1. Log into your admin panel at `/admin`
2. Click **Posts** in the sidebar
3. Click **Add New Post**
4. Write your title and content
5. Add categories and tags if you want
6. Upload a featured image (optional)
7. Set status to **Published**
8. Click **Save**

Your post is now live on your site.

### Managing Users

Go to **Users** in the admin panel. You can:
- Add new users with specific roles
- Edit user profiles
- Change user roles and permissions
- Delete users

The four default roles are:
- **Admin** - Full access to everything
- **Editor** - Can publish and manage all content
- **Author** - Can publish their own content
- **Subscriber** - Can only manage their profile

### Building Navigation Menus

1. Go to **Menus** in the admin panel
2. Create a new menu or edit an existing one
3. Drag items from the left panel into your menu
4. Arrange items with drag-and-drop
5. Assign the menu to a location (Primary, Footer, etc.)
6. Save

Your theme determines where menu locations appear.

### Managing Themes

Go to **Themes** to switch between installed themes. Click **Customize** to adjust colors, fonts, and layouts without touching code.

If you're installing a new theme:
1. Upload the theme folder to `/themes/`
2. Go to **Themes** in admin
3. Click **Activate** on your new theme

---

## Architecture

### MVC Pattern

Pressli follows the Model-View-Controller pattern:

**Models** define your database schema and handle data operations. You write annotations in docblocks, and Pressli generates the database schema:

```php
<?php namespace Models;

use Rackage\Model;

class PostModel extends Model
{
    protected static $table = 'posts';

    /**
     * Post title
     * @column
     * @varchar 255
     */
    protected $title;

    /**
     * Post slug
     * @column
     * @varchar 255
     * @unique
     * @index
     */
    protected $slug;
}
```

**Controllers** handle HTTP requests and prepare data for views. Pressli routes URLs automatically based on controller and method names:

```
/admin/posts          → AdminPostsController::getIndex()
/admin/posts/new      → AdminPostsController::getNew()
/admin/posts/edit/5   → AdminPostsController::getEdit($id)
```

**Views** are PHP templates with a custom syntax for layouts and sections:

```php
@extends('admin/layout')

@section('content')
    <h1>Posts</h1>
    @loopelse($posts as $post)
        <article>{{ $post['title'] }}</article>
    @empty
        <p>No posts yet.</p>
    @endloop
@endsection
```

### Service Layer

I separated business logic from controllers into service classes. This keeps your code reusable and testable.

The service classes are:
- `Lib\Services\Post` - Post creation, updates, slug generation, category syncing
- `Lib\Services\Page` - Page management, hierarchy validation
- `Lib\Services\Comment` - Comment creation, approval, spam marking
- `Lib\Services\Media` - File uploads, validation, metadata
- `Lib\Services\Menu` - Menu and menu item management
- `Lib\Services\Taxonomy` - Category and tag operations

You call services from anywhere - controllers, API endpoints, CLI commands:

```php
use Lib\Services\Post;

// Create a post
$postId = Post::create([
    'title' => 'My First Post',
    'content' => 'Post content here...',
    'status' => 'published',
    'categories' => [1, 2, 3]
], $authorId);
```

Services throw `ServiceException` on validation errors. Catch these in your controllers to show user-friendly messages.

### Provider Registry

Themes don't query the database directly. They declare what data they need, and providers supply it.

Core providers include:
- `related_posts` - Posts in the same category
- `recent_posts` - Latest published posts
- `popular_posts` - Most viewed posts
- `categories` - All categories
- `primary_menu` - Navigation menu items
- `author_bio` - Author information

Your theme's `theme.json` declares providers per template:

```json
{
    "templates": {
        "single": {
            "template": "templates/single.php",
            "data_providers": ["related_posts", "post_comments", "author_bio"]
        }
    }
}
```

Pressli only fetches what you declare. No wasted queries.

### Content Registry

Plugins register custom post types through Content Registry. This lets you extend Pressli without modifying core files.

A plugin might register a "job" post type:

```php
ContentRegistry::register('job', [
    'label' => 'Job Listings',
    'model' => Plugins\Jobs\Models\JobModel::class,
    'controller' => Plugins\Jobs\Controllers\JobsController::class,
    'searchable' => true,
    'supports' => ['title', 'editor', 'categories']
]);
```

Now your plugin has full CRUD capabilities, appears in admin, and integrates with search.

### Theme System

Themes live in `/themes/yourtheme/`. Each theme extends the `Lib\Theme` base class:

```php
<?php namespace Themes\Aurora;

use Lib\Theme;

class AuroraTheme extends Theme
{
    public function boot()
    {
        // Register custom providers
        $this->registerProvider('featured_posts', function($context) {
            return PostModel::where('featured', 1)->limit(3)->all();
        });
    }
}
```

Your `theme.json` defines templates and their data needs. Themes are self-contained - move the folder, and everything moves with it.

### Plugin System

Plugins extend Pressli without touching core code. A plugin is a class that extends `Lib\Plugin`:

```php
<?php namespace Plugins\Jobs;

use Lib\Plugin;

class JobsPlugin extends Plugin
{
    public function boot()
    {
        // Register custom post type
        $this->registerContentType('job', [
            'label' => 'Jobs',
            'model' => Models\JobModel::class,
            'controller' => Controllers\JobsController::class
        ]);
    }

    public function activate()
    {
        // Run migrations when plugin activates
        $this->runMigrations();
    }
}
```

Plugins can register content types, data providers, routes, and database tables. They're fully isolated in their own namespace.

---

## API Documentation

Pressli includes a RESTful API for headless setups or mobile apps. All API endpoints are under `/api/`.

### Authentication

The API uses token-based authentication. You'll implement this based on your security needs - JWT, API keys, or session-based auth.

### Posts API

**List posts**
```
GET /api/posts
```

Query parameters:
- `status` - Filter by status (published, draft)
- `category` - Filter by category ID
- `limit` - Results per page (default: 10)
- `page` - Page number

**Get single post**
```
GET /api/posts/{id}
```

**Create post**
```
POST /api/posts
```

Request body:
```json
{
    "title": "Post Title",
    "content": "Post content...",
    "status": "published",
    "categories": [1, 2]
}
```

**Update post**
```
PUT /api/posts/{id}
```

**Delete post**
```
DELETE /api/posts/{id}
```

### Pages API

Same structure as Posts API, replace `/api/posts` with `/api/pages`.

### Categories API

**List categories**
```
GET /api/categories
```

**Get single category**
```
GET /api/categories/{id}
```

**Create category**
```
POST /api/categories
```

Request body:
```json
{
    "name": "Category Name",
    "slug": "category-slug",
    "description": "Category description"
}
```

### Tags API

Same structure as Categories API, replace `/api/categories` with `/api/tags`.

### Media API

**List media**
```
GET /api/media
```

**Upload media**
```
POST /api/media
Content-Type: multipart/form-data
```

**Get media**
```
GET /api/media/{id}
```

### Comments API

**List comments**
```
GET /api/comments?post_id={id}
```

**Submit comment**
```
POST /api/comments
```

Request body:
```json
{
    "post_id": 1,
    "author_name": "John Doe",
    "author_email": "john@example.com",
    "content": "Comment text..."
}
```

### Response Format

All API responses follow this format:

**Success:**
```json
{
    "success": true,
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "error": "Error message"
}
```

---

## Development

### Creating a Plugin

Plugins extend Pressli without modifying core files. Here's how to build one:

1. **Create your plugin directory**
   ```
   plugins/jobs/
   ├── JobsPlugin.php
   ├── plugin.json
   ├── Models/
   │   └── JobModel.php
   ├── Controllers/
   │   └── JobsController.php
   └── migrations/
       └── 001_create_jobs_table.php
   ```

2. **Write your plugin class**
   ```php
   <?php namespace Plugins\Jobs;

   use Lib\Plugin;

   class JobsPlugin extends Plugin
   {
       public function boot()
       {
           // Register custom content type
           $this->registerContentType('job', [
               'label' => 'Job Listings',
               'model' => Models\JobModel::class,
               'controller' => Controllers\JobsController::class,
               'searchable' => true
           ]);
       }

       public function activate()
       {
           // Run migrations when plugin activates
           $this->runMigrations();
       }

       public function deactivate()
       {
           // Cleanup if needed
       }
   }
   ```

3. **Define plugin metadata in plugin.json**
   ```json
   {
       "name": "Job Listings",
       "version": "1.0.0",
       "author": "Your Name",
       "description": "Add job listings to your site"
   }
   ```

Your plugin appears in the admin panel. Users can activate or deactivate it.

### Creating a Theme

Themes control your site's appearance. Here's the structure:

```
themes/mytheme/
├── MythemeTheme.php
├── theme.json
├── templates/
│   ├── layout.php
│   ├── index.php
│   ├── single.php
│   ├── page.php
│   └── archive.php
└── assets/
    ├── css/
    │   └── style.css
    ├── js/
    │   └── main.js
    └── screenshot.png
```

**Your theme class:**
```php
<?php namespace Themes\Mytheme;

use Lib\Theme;

class MythemeTheme extends Theme
{
    public function boot()
    {
        // Register custom data providers
        $this->registerProvider('featured_posts', function($context) {
            return PostModel::where('featured', 1)->limit(3)->all();
        });
    }
}
```

**Your theme.json:**
```json
{
    "name": "My Theme",
    "version": "1.0.0",
    "author": "Your Name",
    "description": "A beautiful theme",
    "screenshot": "assets/screenshot.png",
    "templates": {
        "single": {
            "template": "templates/single.php",
            "data_providers": ["related_posts", "author_bio", "post_comments"]
        },
        "archive": {
            "template": "templates/archive.php",
            "data_providers": ["categories", "recent_posts"]
        }
    }
}
```

Themes declare what data they need per template. Pressli fetches only that data - no wasted queries.

### Using Services

Services handle business logic. Use them in controllers, API endpoints, or CLI commands:

```php
use Lib\Services\Post;
use Lib\Exceptions\ServiceException;

// Create a post
try {
    $postId = Post::create([
        'title' => 'New Post',
        'content' => 'Content here...',
        'status' => 'published',
        'categories' => [1, 2]
    ], $authorId);
} catch (ServiceException $e) {
    // Handle validation errors
    echo $e->getMessage();
}

// Update a post
Post::update($postId, [
    'title' => 'Updated Title'
]);

// Delete a post (soft delete)
Post::delete($postId);

// Restore a deleted post
Post::restore($postId);
```

Services throw `ServiceException` for validation errors. These exceptions have user-friendly messages you can show directly.

### CLI Commands

Roline gives you commands for development tasks:

**Model commands:**
```bash
php roline model:create Job              # Create model file
php roline model:table-create Job        # Create database table
php roline model:table-update Job        # Update table schema
php roline model:table-schema Job        # Show table structure
```

**Controller commands:**
```bash
php roline controller:create Jobs        # Create controller
```

**Database commands:**
```bash
php roline db:create                     # Create database
php roline db:list                       # List databases
php roline db:tables                     # List tables
php roline db:export backup.sql          # Export database
php roline db:import backup.sql          # Import database
```

**Cache commands:**
```bash
php roline cleanup:cache                 # Clear application cache
php roline cleanup:views                 # Clear compiled views
php roline cleanup:all                   # Clear everything
```

### Project Structure

```
pressli/
├── application/
│   ├── controllers/        # Your controllers
│   ├── models/             # Your models
│   ├── views/              # Admin and auth views
│   ├── libraries/          # Services, Theme, Plugin classes
│   └── database/
│       ├── migrations/     # Database migrations
│       └── seeders/        # Database seeders
├── config/
│   ├── database.php        # Database config
│   ├── settings.php        # App settings
│   └── routes.php          # Custom routes
├── plugins/                # Your plugins
├── themes/                 # Your themes
├── public/
│   ├── index.php           # Entry point
│   ├── admin/              # Admin assets
│   └── uploads/            # User uploads
├── vault/
│   ├── cache/              # Application cache
│   ├── logs/               # Error logs
│   └── tmp/                # Compiled views
└── vendor/                 # Composer dependencies
```

---

## Security

I built security into Pressli from the start. Here's what protects your site:

### Built-in Protection

**SQL Injection Prevention** - All database queries use parameterized statements. Your data stays safe even if someone tries to inject SQL.

**XSS Protection** - The template engine auto-escapes output with `{{ }}`. If you need raw HTML, you explicitly use `{!! !!}` - this makes XSS attacks harder.

**CSRF Protection** - Forms include CSRF tokens. Pressli verifies these on submission to prevent cross-site request forgery.

**Password Security** - Passwords are hashed with bcrypt. When PHP adds stronger algorithms, Pressli automatically rehashes on login.

**Session Security** - Session IDs regenerate after login to prevent session fixation attacks.

**Input Validation** - Services validate all input before saving. You get user-friendly error messages for invalid data.

### Production Checklist

Before you launch your site:

- [ ] Set `'dev' => false` in `config/settings.php`
- [ ] Change any default credentials from development
- [ ] Use strong database passwords
- [ ] Enable HTTPS with valid SSL certificate
- [ ] Set file permissions: 755 for directories, 644 for files
- [ ] Disable directory listing in Apache
- [ ] Turn off error display, turn on error logging
- [ ] Set up regular backups of database and uploads
- [ ] Keep Pressli and dependencies updated

### File Permissions

Use restrictive permissions:

```bash
# Directories
find . -type d -exec chmod 755 {} \;

# Files
find . -type f -exec chmod 644 {} \;

# Writable directories
chmod -R 755 vault/
chmod -R 755 public/uploads/
```

Only `vault/` and `public/uploads/` need write access. Everything else should be read-only.

---

## Contributing

I welcome contributions to Pressli. Whether you're fixing bugs, adding features, or improving docs - your help matters.

### How to Contribute

1. **Fork the repository** on GitHub
2. **Create a feature branch** from `main`
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** - Keep commits focused and clear
4. **Test your changes** - Make sure everything works
5. **Commit with clear messages**
   ```bash
   git commit -m "Add feature: brief description"
   ```
6. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```
7. **Open a Pull Request** against the `main` branch

### Coding Standards

Keep the codebase consistent:

- **PSR-4 autoloading** - Follow the namespace structure
- **Model documentation** - Write 3-line property descriptions in docblocks
- **Controller documentation** - Write 5-line method descriptions covering behavior, performance, and data contracts
- **One annotation per line** - Keep model docblocks clean
- **Service layer** - Put business logic in services, not controllers
- **Validation** - Validate in services, throw `ServiceException` for user errors

### What to Contribute

**Bug fixes** - Always welcome. Include steps to reproduce the bug.

**New features** - Open an issue first to discuss whether it fits Pressli's goals.

**Documentation** - Fix typos, clarify instructions, add examples.

**Themes** - Build themes and share them with the community.

**Plugins** - Extend Pressli with new functionality.

### Getting Help

- **GitHub Issues** - Report bugs or request features
- **Discussions** - Ask questions or share ideas

---

## License

Copyright (c) 2015 - 2030 Geoffrey Okongo

Pressli is open source software licensed under the MIT License. See the LICENSE file for full details.

You're free to use Pressli for personal or commercial projects. Modify it, distribute it, do what you need. Just keep the license notice intact.

---

## Credits

**Author:** Geoffrey Okongo
**Email:** code@rachie.dev
**Website:** https://rachie.dev

**Framework:** Rachie - The PHP framework powering Pressli

**Built with:**
- PHP 8.0+
- MySQL/MariaDB
- Vanilla JavaScript
- CSS Variables

---

## Support

Need help or want to report an issue?

- **Website:** https://pressli.co.ke
- **GitHub:** https://github.com/glivers/pressli
- **Issues:** https://github.com/glivers/pressli/issues
- **Email:** code@rachie.dev
