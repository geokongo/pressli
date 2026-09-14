/**
 * Theme Customizer JavaScript
 *
 * Handles AJAX form submission, live preview updates, and user feedback
 * for the Pressli theme customizer interface.
 */

(function() {
    'use strict';

    // Get DOM elements
    const customizerForm = document.getElementById('customizer-form');
    const saveButton = document.getElementById('save-customizations');
    const previewFrame = document.getElementById('theme-preview');
    const messageContainer = document.getElementById('message-container');

    if (!customizerForm) return;

    /**
     * Show success/error message to user
     */
    function showMessage(message, type = 'success') {
        if (!messageContainer) return;

        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        messageContainer.innerHTML = `
            <div class="alert ${alertClass}">
                ${message}
            </div>
        `;

        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageContainer.innerHTML = '';
        }, 5000);
    }

    /**
     * Submit form via AJAX
     */
    function saveCustomizations(event) {
        event.preventDefault();

        const formData = new FormData(customizerForm);
        const submitButton = event.target;

        // Disable button and show loading state
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        fetch(customizerForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message || 'Settings saved successfully!', 'success');

                // Reload preview iframe to show updated CSS
                if (previewFrame) {
                    previewFrame.contentWindow.location.reload();
                }
            }
            else {
                showMessage(data.message || 'Failed to save settings.', 'error');
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            showMessage('An error occurred while saving. Please try again.', 'error');
        })
        .finally(() => {
            // Re-enable button
            submitButton.disabled = false;
            submitButton.textContent = 'Save Changes';
        });
    }

    /**
     * Sync color picker with text input
     */
    function syncColorInputs() {
        const colorInputs = document.querySelectorAll('input[type="color"]');

        colorInputs.forEach(colorInput => {
            const textInput = document.getElementById(colorInput.id + '-text');
            if (!textInput) return;

            // Update text when color picker changes
            colorInput.addEventListener('input', function() {
                textInput.value = this.value;
                updateLivePreview();
            });

            // Update color picker when text changes
            textInput.addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    colorInput.value = this.value;
                    updateLivePreview();
                }
            });
        });
    }

    /**
     * Sync range slider with display value
     */
    function syncRangeInputs() {
        const rangeInputs = document.querySelectorAll('input[type="range"]');

        rangeInputs.forEach(rangeInput => {
            const display = document.getElementById(rangeInput.id + '-value');
            if (!display) return;

            rangeInput.addEventListener('input', function() {
                display.textContent = this.value;
                updateLivePreview();
            });
        });
    }

    /**
     * Update live preview iframe with current settings
     */
    function updateLivePreview() {
        if (!previewFrame || !previewFrame.contentWindow) return;

        const previewDocument = previewFrame.contentWindow.document;
        if (!previewDocument) return;

        // Find existing custom CSS style tag or create new one
        let styleTag = previewDocument.getElementById('theme-custom-css');

        // Build CSS from current form values
        const css = buildCustomCSS();

        if (styleTag) {
            // Replace existing custom CSS
            styleTag.innerHTML = css;
        }
        else {
            // Create new style tag after main stylesheet
            styleTag = previewDocument.createElement('style');
            styleTag.id = 'theme-custom-css';
            styleTag.innerHTML = css;
            previewDocument.head.appendChild(styleTag);
        }
    }

    /**
     * Build CSS custom properties from form values
     * Only includes properties that have actual values
     */
    function buildCustomCSS() {
        const formData = new FormData(customizerForm);

        let css = ':root {\n';

        // Colors
        if (formData.get('primary_color')) {
            css += `    --primary-color: ${formData.get('primary_color')};\n`;
        }
        if (formData.get('accent_color')) {
            css += `    --accent-color: ${formData.get('accent_color')};\n`;
        }
        if (formData.get('background_color')) {
            css += `    --background-color: ${formData.get('background_color')};\n`;
        }
        if (formData.get('text_color')) {
            css += `    --text-color: ${formData.get('text_color')};\n`;
        }
        if (formData.get('heading_color')) {
            css += `    --heading-color: ${formData.get('heading_color')};\n`;
        }

        // Typography
        if (formData.get('body_font')) {
            css += `    --body-font: ${getFontFamily(formData.get('body_font'))};\n`;
        }
        if (formData.get('heading_font')) {
            css += `    --heading-font: ${getFontFamily(formData.get('heading_font'))};\n`;
        }
        if (formData.get('body_font_size')) {
            css += `    --body-font-size: ${formData.get('body_font_size')}px;\n`;
        }

        // Layout
        if (formData.get('content_width')) {
            css += `    --content-width: ${getContentWidth(formData.get('content_width'))};\n`;
        }

        // Footer
        if (formData.get('footer_background')) {
            css += `    --footer-background: ${formData.get('footer_background')};\n`;
        }
        if (formData.get('footer_columns')) {
            css += `    --footer-columns: ${formData.get('footer_columns')};\n`;
        }

        css += '}\n';

        return css;
    }

    /**
     * Get font family CSS value
     */
    function getFontFamily(font) {
        const fonts = {
            'system': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'inter': '"Inter", sans-serif',
            'roboto': '"Roboto", sans-serif',
            'opensans': '"Open Sans", sans-serif',
            'lato': '"Lato", sans-serif',
            'montserrat': '"Montserrat", sans-serif',
            'playfair': '"Playfair Display", serif',
            'merriweather': '"Merriweather", serif',
            'ptserif': '"PT Serif", serif'
        };
        return fonts[font];
    }

    /**
     * Get content width CSS value
     */
    function getContentWidth(width) {
        const widths = {
            'narrow': '800px',
            'medium': '1000px',
            'wide': '1200px'
        };
        return widths[width];
    }

    /**
     * Debounce function for live preview updates
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize
    function init() {
        // Sync color and range inputs
        syncColorInputs();
        syncRangeInputs();

        // Save button click
        if (saveButton) {
            saveButton.addEventListener('click', saveCustomizations);
        }

        // Live preview on input changes (debounced)
        const debouncedPreview = debounce(updateLivePreview, 300);

        customizerForm.addEventListener('input', function(event) {
            // Skip if it's the color picker (already handled by sync)
            if (event.target.type === 'color') return;

            debouncedPreview();
        });

        customizerForm.addEventListener('change', function(event) {
            // Handle select dropdowns
            if (event.target.tagName === 'SELECT') {
                updateLivePreview();
            }
        });
    }

    // Wait for iframe to load before initializing
    if (previewFrame) {
        previewFrame.addEventListener('load', function() {
            init();
        });
    }
    else {
        init();
    }

})();
