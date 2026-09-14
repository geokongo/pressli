/**
 * Pressli CMS - Posts Page Scripts
 * Minimal JavaScript for table interactions
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initCheckboxes();
        initBulkActions();
    });

    /**
     * Handle select all checkbox and individual row checkboxes
     */
    function initCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-check');

        if (!selectAll || !rowCheckboxes.length) return;

        // Select all functionality
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateBulkActionsState();
        });

        // Individual checkbox functionality
        rowCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                // Update select all state
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);

                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;

                updateBulkActionsState();
            });
        });
    }

    /**
     * Enable/disable bulk actions based on selected rows
     */
    function updateBulkActionsState() {
        const rowCheckboxes = document.querySelectorAll('.row-check');
        const bulkAction = document.getElementById('bulkAction');
        const applyBulk = document.getElementById('applyBulk');

        if (!bulkAction || !applyBulk) return;

        const hasSelection = Array.from(rowCheckboxes).some(cb => cb.checked);

        bulkAction.disabled = !hasSelection;
        applyBulk.disabled = !hasSelection;
    }

    /**
     * Handle bulk actions apply button
     */
    function initBulkActions() {
        const applyBulk = document.getElementById('applyBulk');
        const bulkAction = document.getElementById('bulkAction');

        if (!applyBulk || !bulkAction) return;

        applyBulk.addEventListener('click', function() {
            const action = bulkAction.value;
            const selectedRows = document.querySelectorAll('.row-check:checked');

            if (!action || !selectedRows.length) return;

            // Get selected post titles for confirmation
            const titles = Array.from(selectedRows).map(function(checkbox) {
                const row = checkbox.closest('tr');
                const titleElement = row.querySelector('.post-title');
                return titleElement ? titleElement.textContent : '';
            });

            // Confirm action
            const message = 'Apply "' + bulkAction.options[bulkAction.selectedIndex].text +
                          '" to ' + selectedRows.length + ' post(s)?';

            if (confirm(message)) {
                // Here you would make an AJAX call to your PHP backend
                console.log('Bulk action:', action, 'Posts:', titles);
                alert('Action would be applied to: ' + titles.join(', '));

                // Reset selections
                selectedRows.forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                document.getElementById('selectAll').checked = false;
                updateBulkActionsState();
            }
        });
    }

})();
