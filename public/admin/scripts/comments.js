/**
 * Pressli CMS - Comments Scripts
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initBulkSelect();
        initStatusTabs();
    });

    /**
     * Bulk select functionality
     */
    function initBulkSelect() {
        const selectAll = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-check');
        const bulkAction = document.getElementById('bulkAction');
        const applyBulk = document.getElementById('applyBulk');

        if (!selectAll || !rowCheckboxes.length) return;

        // Select all
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateBulkActionsState();
        });

        // Individual checkboxes
        rowCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);

                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;

                updateBulkActionsState();
            });
        });

        // Apply bulk action
        if (applyBulk) {
            applyBulk.addEventListener('click', function() {
                const action = bulkAction.value;
                const selectedCheckboxes = document.querySelectorAll('.row-check:checked');

                if (!action || !selectedCheckboxes.length) return;

                const actionText = bulkAction.options[bulkAction.selectedIndex].text;
                const message = 'Apply "' + actionText + '" to ' + selectedCheckboxes.length + ' comment(s)?';

                if (confirm(message)) {
                    console.log('Bulk action:', action, 'Comments:', selectedCheckboxes.length);
                    alert('Action would be applied to ' + selectedCheckboxes.length + ' comment(s)');

                    // Reset selections
                    selectedCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = false;
                    });
                    selectAll.checked = false;
                    updateBulkActionsState();
                }
            });
        }

        function updateBulkActionsState() {
            const hasSelection = Array.from(rowCheckboxes).some(cb => cb.checked);
            bulkAction.disabled = !hasSelection;
            applyBulk.disabled = !hasSelection;
        }
    }

    /**
     * Status tab filtering
     */
    function initStatusTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');

        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Remove active class from all tabs
                tabBtns.forEach(function(tab) {
                    tab.classList.remove('active');
                });

                // Add active class to clicked tab
                btn.classList.add('active');

                const filter = btn.getAttribute('data-filter');
                console.log('Filter comments by:', filter);

                // In a real application, this would filter the table rows
                // based on the selected status
            });
        });
    }

})();
