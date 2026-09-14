/**
 * Menus Management JavaScript - Pressli CMS
 * Handles menu and menu item CRUD operations via AJAX
 */

(function() {
    'use strict';

    // Base URL
    //const BASE = window.BASE;

    // BASE is available globally from layout.php  
    // No need to redeclare as const

    // Current menu ID
    let currentMenuId = null;

    // CSRF token helper
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initCreateMenuModal();
        initAddItemHandlers();
        initDeleteHandlers();
        initDragAndDrop();
        initSaveMenu();
        initLocationSelector();
        initEditableTitles();
        loadPages();
        loadCategories();

        // Get current menu ID from URL or first menu
        const urlParams = new URLSearchParams(window.location.search);
        currentMenuId = urlParams.get('menu_id');
    });

    /**
     * Save Menu - Collect all items and save in one request
     */
    function initSaveMenu() {
        const saveBtn = document.querySelector('.form-actions .btn-primary');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (!currentMenuId) {
                alert('No menu selected');
                return;
            }

            // Collect all menu items from DOM
            const items = [];
            const itemMap = new Map(); // Map titles to actual IDs for parent lookup

            document.querySelectorAll('.menu-item-card').forEach((card, index) => {
                const title = card.dataset.title || card.querySelector('.menu-item-title').textContent;
                const itemId = card.dataset.itemId || null;

                // Store mapping for parent lookup
                if (itemId) {
                    itemMap.set(title, itemId);
                }

                let parentId = card.dataset.parentId || '0';

                // If parent_id is a title (for new items), convert to actual ID
                if (parentId !== '0' && itemMap.has(parentId)) {
                    parentId = itemMap.get(parentId);
                }

                items.push({
                    id: itemId,
                    title: title,
                    url: card.dataset.url || card.querySelector('.menu-item-url').textContent,
                    target: card.dataset.target || '_self',
                    parent_id: parentId === '0' ? null : parentId,
                    sort_order: index
                });
            });

            // Show loading state
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            // Send to backend
            fetch(BASE + 'admin/menus/savestructure', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: new URLSearchParams({
                    menu_id: currentMenuId,
                    items: JSON.stringify(items)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload to show saved state
                    window.location.reload();
                } else {
                    alert('Error saving menu: ' + (data.message || 'Unknown error'));
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Menu';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to save menu');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Menu';
            });
        });
    }

    /**
     * Create Menu Modal Functionality
     */
    function initCreateMenuModal() {
        const createBtn = document.querySelector('.content-header .btn-primary');
        const modal = document.getElementById('createMenuModal');
        const closeBtn = document.getElementById('closeCreateModal');
        const cancelBtn = document.getElementById('cancelCreateMenu');
        const submitBtn = document.getElementById('submitCreateMenu');
        const nameInput = document.getElementById('menuName');
        const locationInput = document.getElementById('menuLocation');

        if (!createBtn || !modal) return;

        // Open modal
        createBtn.addEventListener('click', function() {
            modal.classList.add('active');
            nameInput.value = '';
            locationInput.value = '';
            nameInput.focus();
        });

        // Close modal
        const closeModal = () => {
            modal.classList.remove('active');
        };

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.querySelector('.modal-overlay').addEventListener('click', closeModal);

        // Submit form
        submitBtn.addEventListener('click', function() {
            const name = nameInput.value.trim();
            const location = locationInput.value.trim();

            if (!name) {
                alert('Please enter a menu name');
                return;
            }

            // Show loading state - DON'T close modal yet
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            createMenu(name, location, submitBtn, closeModal);
        });

        // Enter key submits
        [nameInput, locationInput].forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    submitBtn.click();
                }
            });
        });
    }

    function createMenu(name, location, submitBtn, closeModal) {
        const formData = new FormData();
        formData.append('name', name);
        formData.append('location', location);

        fetch(BASE + 'admin/menus/create', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal on success
                closeModal();
                // Redirect to new menu
                window.location.href = BASE + 'admin/menus?menu_id=' + data.menu_id;
            } else {
                // Show error and restore button
                alert('Error creating menu: ' + (data.message || 'Unknown error'));
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Menu';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to create menu');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Menu';
        });
    }

    /**
     * Delete Menu Handler
     */
    function initDeleteHandlers() {
        // Delete menu button (this one still saves immediately)
        const deleteMenuBtn = document.querySelector('.btn-danger-outline');
        if (deleteMenuBtn) {
            deleteMenuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const menuId = this.dataset.menuId;

                if (!confirm('Delete this menu? All menu items will also be deleted.')) {
                    return;
                }

                deleteMenu(menuId);
            });
        }

        // Delete menu item buttons (DOM only - no save)
        document.querySelectorAll('.menu-item-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Delete this menu item?')) {
                    return;
                }

                const itemCard = this.closest('.menu-item-card');
                itemCard.remove();

                // Show empty state if no items left
                const itemsList = document.querySelector('.menu-items-list');
                if (itemsList.children.length === 0) {
                    itemsList.innerHTML = '<p style="color: var(--text-tertiary); text-align: center; padding: var(--spacing-lg);">No menu items yet. Add items below.</p>';
                }
            });
        });
    }

    function deleteMenu(menuId) {
        fetch(BASE + 'admin/menus/delete/' + menuId, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = BASE + 'admin/menus';
            } else {
                alert('Error deleting menu: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete menu');
        });
    }

    /**
     * Make existing menu item titles editable
     */
    function initEditableTitles() {
        document.querySelectorAll('.menu-item-card').forEach(itemCard => {
            const titleEl = itemCard.querySelector('.menu-item-title');
            if (!titleEl) return;

            // Make contenteditable
            titleEl.setAttribute('contenteditable', 'true');
            titleEl.setAttribute('spellcheck', 'false');

            // Update dataset on blur
            titleEl.addEventListener('blur', function() {
                const newTitle = this.textContent.trim();
                if (newTitle) {
                    itemCard.dataset.title = newTitle;
                }
                else {
                    // Restore original if empty
                    this.textContent = itemCard.dataset.title;
                }
            });

            // Submit on Enter key
            titleEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });
    }

    /**
     * Add Menu Items Functionality
     */
    function initAddItemHandlers() {
        // Pages checkboxes (first section in sidebar)
        const pagesBtn = document.querySelectorAll('.menu-add-section')[0]?.querySelector('.btn');
        if (pagesBtn) {
            pagesBtn.addEventListener('click', function() {
                if (!currentMenuId) {
                    alert('Please select a menu first');
                    return;
                }
                const section = this.closest('.menu-add-section');
                const checkboxes = section.querySelectorAll('input[type="checkbox"]:checked');

                checkboxes.forEach(checkbox => {
                    const title = checkbox.dataset.title || checkbox.closest('label').textContent.trim();
                    const url = checkbox.dataset.url || '/' + title.toLowerCase().replace(/\s+/g, '-');
                    addMenuItem(title, url);
                    checkbox.checked = false;
                });
            });
        }

        // Custom link (second section in sidebar)
        const customLinkBtn = document.querySelectorAll('.menu-add-section')[1]?.querySelector('.btn');
        if (customLinkBtn) {
            customLinkBtn.addEventListener('click', function() {
                if (!currentMenuId) {
                    alert('Please select a menu first');
                    return;
                }
                const url = document.getElementById('custom-url').value;
                const label = document.getElementById('custom-label').value;

                if (!url || !label) {
                    alert('Please enter both URL and link text');
                    return;
                }

                addMenuItem(label, url);
            });
        }

        // Categories checkboxes (third section in sidebar)
        const categoriesBtn = document.querySelectorAll('.menu-add-section')[2]?.querySelector('.btn');
        if (categoriesBtn) {
            categoriesBtn.addEventListener('click', function() {
                if (!currentMenuId) {
                    alert('Please select a menu first');
                    return;
                }
                const section = this.closest('.menu-add-section');
                const checkboxes = section.querySelectorAll('input[type="checkbox"]:checked');

                checkboxes.forEach(checkbox => {
                    const title = checkbox.dataset.title || checkbox.closest('label').textContent.trim();
                    const url = checkbox.dataset.url || '/category/' + title.toLowerCase().replace(/\s+/g, '-');
                    addMenuItem(title, url);
                    checkbox.checked = false;
                });
            });
        }
    }

    function addMenuItem(title, url, target = '_self') {
        if (!currentMenuId) {
            alert('Please select a menu first');
            return;
        }

        // Add to DOM only - don't save yet
        const itemsList = document.querySelector('.menu-items-list');
        if (!itemsList) return;

        // Remove empty state if exists
        const emptyState = itemsList.querySelector('p');
        if (emptyState) emptyState.remove();

        // Create menu item card
        const itemCard = document.createElement('div');
        itemCard.className = 'menu-item-card';
        itemCard.setAttribute('draggable', 'true');
        itemCard.dataset.title = title;
        itemCard.dataset.url = url;
        itemCard.dataset.target = target;
        itemCard.dataset.parentId = '0'; // Top level by default
        itemCard.dataset.isNew = 'true'; // Mark as unsaved

        itemCard.innerHTML = `
            <div class="menu-item-handle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </div>
            <div class="menu-item-content">
                <div class="menu-item-title" contenteditable="true" spellcheck="false">${title}</div>
                <div class="menu-item-url">${url}</div>
            </div>
            <button class="menu-item-delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </button>
        `;

        itemsList.appendChild(itemCard);

        // Make title editable and sync with dataset
        const titleEl = itemCard.querySelector('.menu-item-title');
        titleEl.addEventListener('blur', function() {
            const newTitle = this.textContent.trim();
            if (newTitle) {
                itemCard.dataset.title = newTitle;
            }
            else {
                // Restore original if empty
                this.textContent = itemCard.dataset.title;
            }
        });

        titleEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
        });

        // Attach delete handler to new item
        itemCard.querySelector('.menu-item-delete').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Delete this menu item?')) {
                itemCard.remove();
                // Show empty state if no items left
                if (itemsList.children.length === 0) {
                    itemsList.innerHTML = '<p style="color: var(--text-tertiary); text-align: center; padding: var(--spacing-lg);">No menu items yet. Add items below.</p>';
                }
            }
        });
    }

    /**
     * Drag and Drop Reordering with 1-level nesting
     */
    function initDragAndDrop() {
        const itemsList = document.querySelector('.menu-items-list');
        if (!itemsList) return;

        let draggedItem = null;

        itemsList.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('menu-item-card')) {
                draggedItem = e.target;
                e.target.style.opacity = '0.5';
            }
        });

        itemsList.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('menu-item-card')) {
                e.target.style.opacity = '';
                draggedItem = null;
                // Remove all nesting indicators
                document.querySelectorAll('.menu-item-card').forEach(item => {
                    item.classList.remove('nest-indicator');
                });
            }
        });

        itemsList.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!draggedItem) return;

            const afterElement = getDragAfterElement(itemsList, e.clientY, e.clientX);

            if (afterElement == null) {
                itemsList.appendChild(draggedItem);
                makeTopLevel(draggedItem);
            } else if (afterElement.shouldNest) {
                // Make it a child of the element above
                itemsList.insertBefore(draggedItem, afterElement.element.nextSibling);
                makeChild(draggedItem, afterElement.element);
                afterElement.element.classList.add('nest-indicator');
            } else {
                itemsList.insertBefore(draggedItem, afterElement.element);
                makeTopLevel(draggedItem);
                afterElement.element.classList.remove('nest-indicator');
            }
        });

        itemsList.addEventListener('drop', function(e) {
            e.preventDefault();
            // Remove all nesting indicators
            document.querySelectorAll('.menu-item-card').forEach(item => {
                item.classList.remove('nest-indicator');
            });
        });

        // Items are already draggable from HTML, padding set server-side
    }

    function makeChild(item, parent) {
        item.dataset.parentId = parent.dataset.itemId || parent.dataset.title; // Use title as fallback for new items
        item.classList.add('nested');
    }

    function makeTopLevel(item) {
        item.dataset.parentId = '0';
        item.classList.remove('nested');
    }

    function getDragAfterElement(container, y, x) {
        const draggableElements = [...container.querySelectorAll('.menu-item-card:not(.dragging)')];
        const NEST_THRESHOLD = 30; // Pixels to the right to trigger nesting

        const result = draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY });

        if (!result.element) return null;

        const box = result.element.getBoundingClientRect();
        const isIndented = x > box.left + NEST_THRESHOLD;

        // Only allow nesting if target is NOT already a child (1 level max)
        const canNest = result.element.dataset.parentId === '0';
        const shouldNest = isIndented && canNest;

        return {
            element: result.element,
            shouldNest: shouldNest
        };
    }

    /**
     * Load pages from API and populate list
     */
    function loadPages() {
        fetch(BASE + 'admin/menus/pages')
            .then(response => response.json())
            .then(data => {
                const pagesSection = document.querySelectorAll('.menu-add-section')[0];
                if (!pagesSection) return;

                const body = pagesSection.querySelector('.menu-add-section-body');
                body.innerHTML = '';

                if (data.pages && data.pages.length > 0) {
                    data.pages.forEach(page => {
                        const label = document.createElement('label');
                        label.className = 'form-label';
                        label.innerHTML = `<input type="checkbox" data-url="/${page.slug}" data-title="${page.title}"> ${page.title}`;
                        body.appendChild(label);
                    });
                } else {
                    body.innerHTML = '<p style="color: var(--text-tertiary); font-size: 13px;">No published pages</p>';
                }
            })
            .catch(error => console.error('Failed to load pages:', error));
    }

    /**
     * Load categories from API and populate list
     */
    function loadCategories() {
        fetch(BASE + 'admin/menus/categories')
            .then(response => response.json())
            .then(data => {
                const categoriesSection = document.querySelectorAll('.menu-add-section')[2];
                if (!categoriesSection) return;

                const body = categoriesSection.querySelector('.menu-add-section-body');
                body.innerHTML = '';

                if (data.categories && data.categories.length > 0) {
                    data.categories.forEach(category => {
                        const label = document.createElement('label');
                        label.className = 'form-label';
                        label.innerHTML = `<input type="checkbox" data-url="/category/${category.slug}" data-title="${category.name}"> ${category.name}`;
                        body.appendChild(label);
                    });
                } else {
                    body.innerHTML = '<p style="color: var(--text-tertiary); font-size: 13px;">No categories</p>';
                }
            })
            .catch(error => console.error('Failed to load categories:', error));
    }

    /**
     * Initialize menu location selector
     */
    function initLocationSelector() {
        const locationSelect = document.getElementById('menuLocation');
        if (!locationSelect) return;

        locationSelect.addEventListener('change', function() {
            if (!currentMenuId) {
                alert('No menu selected');
                return;
            }

            const location = this.value;

            // Update menu location via AJAX
            fetch(BASE + 'admin/menus/updatelocation', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: new URLSearchParams({
                    menu_id: currentMenuId,
                    location: location
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to update menu location: ' + (data.message || 'Unknown error'));
                }
                // Success - location updated silently
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update menu location');
            });
        });
    }

})();
