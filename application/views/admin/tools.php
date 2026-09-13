@extends('admin/layout')

@section('content')
<main class="content">
    <div class="content-header">
        <h1 class="page-title">Tools</h1>
        <p class="page-description">Advanced administrative tools for site maintenance and management</p>
    </div>

    @if(Session::hasFlash('success'))
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            {{ Session::flash('success') }}
        </div>
    @endif

    @if(Session::hasFlash('error'))
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
            {{ Session::flash('error') }}
        </div>
    @endif

    <div class="tools-grid">
        <!-- Left Column -->
        <div>
            <!-- System Information -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">System Information</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Pressli Version</span>
                            <span class="info-value">{{ $systemInfo['pressli_version'] }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value">{{ $systemInfo['php_version'] }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">MySQL Version</span>
                            <span class="info-value">{{ $systemInfo['mysql_version'] }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Max Upload Size</span>
                            <span class="info-value">{{ $systemInfo['upload_max_size'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Replace URLs -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">🔄 Search & Replace URLs</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" style="margin-bottom: 1rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                        <strong>⚠️ Warning:</strong> Backup your database before running this tool. This operation cannot be undone.
                    </div>

                    <p style="margin-bottom: 1rem; color: #666;">
                        Use this tool when moving your site to a new domain. It will find and replace all occurrences of the old URL in your database and theme files.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="old-url">Old URL</label>
                        <input type="text" id="old-url" class="text-input" placeholder="http://localhost/mysite" value="{{ Url::base() }}">
                        <p class="form-help">The URL to search for and replace</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new-url">New URL</label>
                        <input type="text" id="new-url" class="text-input" placeholder="https://mynewsite.com">
                        <p class="form-help">The replacement URL</p>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="include-themes" checked>
                            <span>Include active theme files</span>
                        </label>
                        <p class="form-help" style="margin-left: 1.5rem;">Scans PHP templates for hardcoded URLs</p>
                    </div>

                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="preview-btn" class="btn btn-secondary">Preview Changes</button>
                        <button type="button" id="replace-btn" class="btn btn-primary" style="display: none;">Execute Replace</button>
                    </div>

                    <div id="replace-preview" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">
                        <h4 style="margin: 0 0 0.5rem 0;">Preview Results:</h4>
                        <div id="preview-content"></div>
                    </div>

                    <div id="replace-result" style="margin-top: 1rem;"></div>
                </div>
            </div>

            <!-- Sitemap Generator -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">🗺️ Sitemap Generator</h2>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem; color: #666;">
                        Generate an XML sitemap for search engines. Includes homepage, published posts, pages, categories, and tags.
                    </p>

                    <button type="button" id="generate-sitemap-btn" class="btn btn-primary">Generate Sitemap</button>

                    <div id="sitemap-result" style="margin-top: 1rem;"></div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.875rem; font-weight: 600;">What's included:</h4>
                        <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: #666;">
                            <li>Homepage</li>
                            <li>All published pages</li>
                            <li>All published posts</li>
                            <li>Category archive pages</li>
                            <li>Tag archive pages</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Robots.txt Generator -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">🤖 Robots.txt Generator</h2>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem; color: #666;">
                        Generate robots.txt file to control search engine crawlers. Includes sitemap reference and blocks admin areas.
                    </p>

                    <button type="button" id="generate-robots-btn" class="btn btn-primary">Generate Robots.txt</button>

                    <div id="robots-result" style="margin-top: 1rem;"></div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.875rem; font-weight: 600;">What's included:</h4>
                        <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: #666;">
                            <li>Allow all search engine crawlers</li>
                            <li>Block /admin/ and /vault/ directories</li>
                            <li>Reference to sitemap.xml</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Update Pressli -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">⬆️ Update Pressli</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 1rem; padding: 0.75rem; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
                        <strong>ℹ️ Current Version:</strong> {{ $systemInfo['pressli_version'] }}
                        @isset($systemInfo['new_version'])
                            <a href="{{Url::link('admin/tools/update')}}">Click here to update to Pressli v{{$systemInfo['new_version']}}</a>
                        @endif
                    </div>

                    <p style="margin-bottom: 1rem; color: #666;">
                        Upload a Pressli update package (.zip file) to update your installation. A backup will be created automatically before updating.
                    </p>

                    <form method="POST" action="{{ Url::link('admin/tools/update') }}" enctype="multipart/form-data">
                        {{{ Csrf::field() }}}

                        <div class="form-group">
                            <label class="form-label" for="update-file">Update Package</label>
                            <input type="file" id="update-file" name="update_file" class="file-input" accept=".zip" required>
                            <p class="form-help">Maximum file size: {{ $systemInfo['upload_max_size'] }}</p>
                        </div>

                        <div class="alert alert-warning" style="margin-bottom: 1rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                            <strong>⚠️ Important:</strong>
                            <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
                                <li>Backup your site before updating</li>
                                <li>Your config and vault folders will NOT be modified</li>
                                <li>Custom theme files will be preserved</li>
                                <li>Database migrations may run after update</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary" id="update-btn">
                            Upload & Update
                        </button>
                    </form>

                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #dee2e6;">
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.875rem; font-weight: 600;">Update Process:</h4>
                        <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: #666;">
                            <li>Upload validates the package structure</li>
                            <li>Automatic backup is created in vault/backups/</li>
                            <li>Core files are updated (application, public, vendor)</li>
                            <li>Your config and custom themes remain untouched</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Future Tools Placeholder -->
            <div class="card" style="margin-bottom: 1.5rem; opacity: 0.6;">
                <div class="card-header">
                    <h2 class="card-title">🔧 Coming Soon</h2>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.25rem; color: #666;">
                        <li>Database Backup & Restore</li>
                        <li>Import/Export Content</li>
                        <li>Clear Cache & Sessions</li>
                        <li>System Health Check</li>
                        <li>Error Log Viewer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.tools-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .tools-grid {
        grid-template-columns: 1fr;
    }
}

.page-description {
    margin: 0.5rem 0 0 0;
    color: #666;
    font-size: 0.875rem;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.info-label {
    font-weight: 500;
    color: #495057;
}

.info-value {
    font-family: 'Courier New', monospace;
    color: #212529;
}

.alert-info {
    color: #0c5460;
}

.alert-warning {
    color: #856404;
}

.file-input {
    display: block;
    width: 100%;
    padding: 0.5rem;
    border: 2px dashed #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.2s;
}

.file-input:hover {
    border-color: #adb5bd;
    background: #e9ecef;
}
</style>
@endsection

@section('scripts')
    @parent
    <script>
        // Search & Replace Tool
        const previewBtn = document.getElementById('preview-btn');
        const replaceBtn = document.getElementById('replace-btn');
        const previewDiv = document.getElementById('replace-preview');
        const previewContent = document.getElementById('preview-content');
        const resultDiv = document.getElementById('replace-result');

        previewBtn.addEventListener('click', async function() {
            const oldUrl = document.getElementById('old-url').value.trim();
            const newUrl = document.getElementById('new-url').value.trim();
            const includeThemes = document.getElementById('include-themes').checked;

            if (!oldUrl || !newUrl) {
                alert('Please enter both old and new URLs');
                return;
            }

            if (oldUrl === newUrl) {
                alert('Old and new URLs cannot be the same');
                return;
            }

            previewBtn.disabled = true;
            previewBtn.textContent = 'Searching...';
            resultDiv.innerHTML = '';

            try {
                const response = await fetch('{{ Url::link("admin/tools/search") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ oldUrl, newUrl, includeThemes })
                });

                const result = await response.json();

                if (result.success) {
                    previewContent.innerHTML = `
                        <div style="margin-bottom: 0.75rem;"><strong>Database:</strong> ${result.database.total} occurrences found</div>
                        <ul style="margin: 0 0 0.75rem 0; padding-left: 1.5rem; font-size: 0.875rem;">
                            <li>Posts content: ${result.database.posts_content}</li>
                            <li>Posts excerpts: ${result.database.posts_excerpt}</li>
                            <li>Settings: ${result.database.settings}</li>
                            <li>Media paths: ${result.database.media}</li>
                        </ul>
                        ${result.theme.total > 0 ? `
                        <div style="margin-bottom: 0.5rem;"><strong>Theme files:</strong> ${result.theme.total} occurrences in ${result.theme.files.length} files</div>
                        <ul style="margin: 0 0 0.75rem 0; padding-left: 1.5rem; font-size: 0.875rem;">
                            ${result.theme.files.slice(0, 5).map(f => `<li>${f}</li>`).join('')}
                            ${result.theme.files.length > 5 ? `<li><em>...and ${result.theme.files.length - 5} more</em></li>` : ''}
                        </ul>
                        ` : '<div style="margin-bottom: 0.5rem;"><strong>Theme files:</strong> No occurrences found</div>'}
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #dee2e6; font-weight: bold;">
                            Total: ${result.database.total + result.theme.total} replacements will be made
                        </div>
                    `;
                    previewDiv.style.display = 'block';
                    replaceBtn.style.display = result.database.total + result.theme.total > 0 ? 'inline-block' : 'none';
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Failed to preview changes: ' + error.message);
            }

            previewBtn.disabled = false;
            previewBtn.textContent = 'Preview Changes';
        });

        replaceBtn.addEventListener('click', async function() {
            if (!confirm('⚠️ WARNING: This will permanently replace URLs in your database and theme files.\n\nHave you backed up your database?\n\nClick OK to proceed.')) {
                return;
            }

            const oldUrl = document.getElementById('old-url').value.trim();
            const newUrl = document.getElementById('new-url').value.trim();
            const includeThemes = document.getElementById('include-themes').checked;

            replaceBtn.disabled = true;
            replaceBtn.textContent = 'Replacing...';

            try {
                const response = await fetch('{{ Url::link("admin/tools/replace") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ oldUrl, newUrl, includeThemes })
                });

                const result = await response.json();

                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✓ Replacement complete!</strong><br>
                            <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
                                <li>Database: ${result.databaseReplacements} replacements</li>
                                <li>Theme files: ${result.themeReplacements} files modified</li>
                                <li><strong>Total: ${result.totalReplacements} changes made</strong></li>
                            </ul>
                        </div>
                    `;
                    previewDiv.style.display = 'none';
                    replaceBtn.style.display = 'none';

                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <strong>Error:</strong> ${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
            }

            replaceBtn.disabled = false;
            replaceBtn.textContent = 'Execute Replace';
        });

        // Update form validation
        const updateForm = document.querySelector('form[action*="tools/update"]');
        if (updateForm) {
            updateForm.addEventListener('submit', function(e) {
                const fileInput = document.getElementById('update-file');

                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    alert('Please select a .zip file to upload');
                    return;
                }

                const fileName = fileInput.files[0].name;
                if (!fileName.toLowerCase().endsWith('.zip')) {
                    e.preventDefault();
                    alert('Only .zip files are allowed');
                    return;
                }

                if (!confirm('⚠️ WARNING: This will update Pressli core files.\n\nA backup will be created automatically.\n\nClick OK to proceed.')) {
                    e.preventDefault();
                    return;
                }

                document.getElementById('update-btn').disabled = true;
                document.getElementById('update-btn').textContent = 'Uploading & Updating...';
            });
        }

        // Sitemap Generator
        const sitemapBtn = document.getElementById('generate-sitemap-btn');
        if (sitemapBtn) {
            sitemapBtn.addEventListener('click', async function() {
                const resultDiv = document.getElementById('sitemap-result');
                sitemapBtn.disabled = true;
                sitemapBtn.textContent = 'Generating...';
                resultDiv.innerHTML = '';

                try {
                    const response = await fetch('{{ Url::link("admin/tools/sitemap") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success" style="padding: 0.75rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                                <strong>✓ ${data.message}</strong><br>
                                <a href="${data.sitemapUrl}" target="_blank" style="color: #155724; text-decoration: underline;">View Sitemap</a>
                            </div>
                        `;
                    }
                    else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-error" style="padding: 0.75rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                                <strong>✗ ${data.message}</strong>
                            </div>
                        `;
                    }
                }
                catch (error) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error" style="padding: 0.75rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                            <strong>✗ Error: ${error.message}</strong>
                        </div>
                    `;
                }
                finally {
                    sitemapBtn.disabled = false;
                    sitemapBtn.textContent = 'Generate Sitemap';
                }
            });
        }

        // Robots.txt Generator
        const robotsBtn = document.getElementById('generate-robots-btn');
        if (robotsBtn) {
            robotsBtn.addEventListener('click', async function() {
                const resultDiv = document.getElementById('robots-result');
                robotsBtn.disabled = true;
                robotsBtn.textContent = 'Generating...';
                resultDiv.innerHTML = '';

                try {
                    const response = await fetch('{{ Url::link("admin/tools/robots") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success" style="padding: 0.75rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                                <strong>✓ ${data.message}</strong><br>
                                <a href="${data.robotsUrl}" target="_blank" style="color: #155724; text-decoration: underline;">View Robots.txt</a>
                            </div>
                        `;
                    }
                    else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-error" style="padding: 0.75rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                                <strong>✗ ${data.message}</strong>
                            </div>
                        `;
                    }
                }
                catch (error) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error" style="padding: 0.75rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                            <strong>✗ Error: ${error.message}</strong>
                        </div>
                    `;
                }
                finally {
                    robotsBtn.disabled = false;
                    robotsBtn.textContent = 'Generate Robots.txt';
                }
            });
        }
    </script>
@endsection
