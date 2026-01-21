/* Fluid Design Tokens - Admin JavaScript */
jQuery(document).ready(function ($) {
    console.log('FDT: Admin script loaded');

    // Check if required objects exist
    if (typeof fdt_ajax === 'undefined') {
        console.error('FDT: fdt_ajax object not found');
        return;
    }

    console.log('FDT: AJAX URL:', fdt_ajax.ajax_url);
    console.log('FDT: Nonce:', fdt_ajax.nonce);

    // Update settings display on page load
    updateCurrentSettingsDisplay();

    // Calculate fluid value function
    function calculateFluidValue(minRem, maxRem) {
        // If min and max are equal, return the fixed value
        if (minRem === maxRem) {
            return minRem + 'rem';
        }

        // Initialize viewport variables
        var minVw = 320; // Default fallback
        var maxVw = 1140; // Default fallback

        // Get viewport range from server
        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_get_viewport',
                nonce: fdt_ajax.nonce
            },
            async: false, // We need this to be synchronous for the calculation
            success: function (response) {
                console.log('FDT: Viewport response:', response);
                if (response.success && response.data && response.data.range) {
                    minVw = parseInt(response.data.range.min) || minVw;
                    maxVw = parseInt(response.data.range.max) || maxVw;
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Failed to get viewport range:', error);
            }
        });

        // Get current root font size setting
        var rootFontSize = $('input[name="root_font_size"]:checked').val() || '62.5%';
        var baseFontSize = (rootFontSize === '62.5%') ? 10 : 16; // px per rem

        // Convert viewport widths to rem for calculations
        var minVwRem = minVw / baseFontSize;
        var maxVwRem = maxVw / baseFontSize;

        // Calculate slope and intercept
        var slope = (maxRem - minRem) / (maxVwRem - minVwRem);
        var intercept = minRem - (slope * minVwRem);
        var slopeVw = slope * 100;

        // Round to 3 decimal places
        slopeVw = Math.round(slopeVw * 1000) / 1000;
        intercept = Math.round(intercept * 1000) / 1000;

        // Format output
        if (intercept >= 0) {
            return slopeVw + 'vw + ' + intercept + 'rem';
        } else {
            return slopeVw + 'vw - ' + Math.abs(intercept) + 'rem';
        }
    }

    // Handle root font size changes
    $('input[name="root_font_size"]').on('change', function () {
        console.log('FDT: Root font size changed to:', $(this).val());
        updateAllTokenValues();
    });

    // Handle settings save
    $('#save-settings').on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var rootSize = $('input[name="root_font_size"]:checked').val();

        if (!rootSize) {
            showNotice('Please select a root font size.', 'error');
            return;
        }

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_update_settings',
                root_font_size: rootSize,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Settings response:', response);
                $button.prop('disabled', false).text('Save Settings');

                if (response.success) {
                    showNotice('Settings saved successfully!', 'success');
                    updateCurrentSettingsDisplay();
                    updateAllTokenValues();
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Settings save failed:', error);
                $button.prop('disabled', false).text('Save Settings');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Handle token addition
    $('#add-token-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var name = $('#token-name').val().trim();
        var min = parseFloat($('#token-min').val());
        var max = parseFloat($('#token-max').val());

        console.log('FDT: Adding token:', { name, min, max });

        if (!validateTokenInput(name, min, max)) {
            return;
        }

        $button.prop('disabled', true).text('Adding...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_add_token',
                name: name,
                min: min,
                max: max,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Add token response:', response);
                $button.prop('disabled', false).text('Add Token');

                if (response.success) {
                    showNotice("Token '--" + name + "' added successfully!", 'success');
                    $form[0].reset();
                    addTokenToList(name, min, max);
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Add token failed:', error);
                $button.prop('disabled', false).text('Add Token');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Handle token editing
    $(document).on('click', '.fdt-edit-token', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $token = $button.closest('.fdt-token');
        var name = $button.data('name');
        var tokenData = getTokenData($token);

        console.log('FDT: Editing token:', { name, tokenData });

        // Populate edit form
        $('#edit-original-name').val(name);
        $('#edit-token-name').val(name);
        $('#edit-token-min').val(tokenData.min);
        $('#edit-token-max').val(tokenData.max);

        // Show modal
        $('#edit-token-modal').fadeIn(300);
        setTimeout(function () {
            $('#edit-token-name').focus().select();
        }, 350);
    });

    // Handle edit form submission
    $('#edit-token-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalName = $('#edit-original-name').val();
        var name = $('#edit-token-name').val().trim();
        var min = parseFloat($('#edit-token-min').val());
        var max = parseFloat($('#edit-token-max').val());

        console.log('FDT: Updating token:', { originalName, name, min, max });

        if (!validateTokenInput(name, min, max)) {
            return;
        }

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_edit_token',
                original_name: originalName,
                name: name,
                min: min,
                max: max,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Edit token response:', response);
                $button.prop('disabled', false).text('Save Changes');

                if (response.success) {
                    showNotice("Token '--" + name + "' updated successfully!", 'success');
                    $('#edit-token-modal').fadeOut(300);
                    updateTokenInList(originalName, name, min, max);
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Edit token failed:', error);
                $button.prop('disabled', false).text('Save Changes');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Token deletion state
    var tokenToDelete = null;
    var $tokenElementToDelete = null;

    // Handle token deletion click
    $(document).on('click', '.fdt-delete-token, .fdt-delete-static-token', function (e) {
        e.preventDefault();

        var $button = $(this);
        var name = $button.data('name');

        tokenToDelete = name;
        $tokenElementToDelete = $button.closest('.fdt-token');

        var isStatic = $button.hasClass('fdt-delete-static-token');
        $('#delete-token-modal').data('is-static', isStatic);

        $('#delete-token-message').text("Are you sure you want to delete the token '--" + (isStatic ? "fs-" : "") + name + "'?");
        $('#delete-token-modal').fadeIn(300);
    });

    // Handle confirm delete
    $('.fdt-confirm-delete').on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var name = tokenToDelete;
        var $token = $tokenElementToDelete;
        var isStatic = $('#delete-token-modal').data('is-static');
        var action = isStatic ? 'fdt_delete_static_token' : 'fdt_delete_token';

        if (!name || !$token) return;

        console.log('FDT: Deleting token:', name);

        $button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                name: name,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Delete token response:', response);
                $button.prop('disabled', false).text('Delete');

                if (response.success) {
                    $('#delete-token-modal').fadeOut(300);
                    $token.fadeOut(300, function () {
                        $(this).remove();
                        if (!isStatic) checkEmptyTokenList();
                        else checkEmptyStaticTokensList();
                    });
                    showNotice("Token '" + name + "' deleted successfully!", 'success');
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Delete token failed:', error);
                $button.prop('disabled', false).text('Delete');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Handle cancel delete
    $('#cancel-delete').on('click', function () {
        $('#delete-token-modal').fadeOut(300);
        tokenToDelete = null;
        $tokenElementToDelete = null;
    });

    // Generic Modal controls
    $('.fdt-modal-close').on('click', function (e) {
        e.preventDefault();
        $(this).closest('.fdt-modal').fadeOut(300);
    });

    $('#cancel-edit').on('click', function (e) {
        e.preventDefault();
        closeEditModal();
    });

    $('.fdt-modal').on('click', function (e) {
        if ($(e.target).hasClass('fdt-modal')) {
            $(this).fadeOut(300);
        }
    });

    $(document).on('keyup', function (e) {
        if (e.keyCode === 27) { // Escape key
            $('.fdt-modal').fadeOut(300);
        }
    });

    // Utility functions
    function validateTokenInput(name, min, max) {
        // Clean token name
        name = name.toLowerCase().replace(/[^a-z0-9-]/g, '');

        if (!name) {
            showNotice('Token name must contain letters or numbers.', 'error');
            return false;
        }

        if (isNaN(min) || isNaN(max)) {
            showNotice('Please enter valid numbers for min and max values.', 'error');
            return false;
        }

        if (min <= 0 || max <= 0) {
            showNotice('Min and max values must be positive.', 'error');
            return false;
        }

        // Check if token already exists (for new tokens)
        var originalName = $('#edit-original-name').val();
        if (!originalName && $('.fdt-token[data-name="' + name + '"]').length > 0) {
            showNotice('A token with this name already exists.', 'error');
            return false;
        }

        return true;
    }

    function addTokenToList(name, min, max) {
        var fluidValue = calculateFluidValue(min, max);
        var $container = $('#tokens-list');

        // Remove "no tokens" message
        $container.find('.fdt-no-tokens').remove();

        var tokenHtml = createTokenHtml(name, min, max, fluidValue);
        var $newToken = $(tokenHtml);
        $container.prepend($newToken);

        // Success animation
        $newToken.addClass('fdt-success');
        setTimeout(function () {
            $newToken.removeClass('fdt-success');
        }, 2000);
    }

    function updateTokenInList(originalName, newName, min, max) {
        var $oldToken = $('.fdt-token[data-name="' + originalName + '"]');
        var fluidValue = calculateFluidValue(min, max);

        var tokenHtml = createTokenHtml(newName, min, max, fluidValue);
        var $newToken = $(tokenHtml);

        $oldToken.replaceWith($newToken);

        // Success animation
        $newToken.addClass('fdt-success');
        setTimeout(function () {
            $newToken.removeClass('fdt-success');
        }, 2000);
    }

    function createTokenHtml(name, min, max, fluidValue) {
        var isFixed = min === max;
        var valueDisplay = isFixed ?
            min + 'rem' :
            'clamp(' + min + 'rem, ' + fluidValue + ', ' + max + 'rem)';
        var rangeDisplay = isFixed ?
            '(fixed value)' :
            '(' + min + 'rem → ' + max + 'rem)';

        return '<div class="fdt-token" data-name="' + name + '">' +
            '<div class="fdt-token-info">' +
            '<div class="fdt-token-name-wrapper">' +
            '<strong>--' + name + '</strong>' +
            '<button type="button" class="fdt-copy-token" data-token="var(--' + name + ')" title="Copy token">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
            '</button>' +
            '</div>' +
            '<span class="fdt-token-value">' + valueDisplay + '</span>' +
            '<span class="fdt-token-range">' + rangeDisplay + '</span>' +
            '</div>' +
            '<div class="fdt-token-actions">' +
            '<button class="fdt-edit-token" data-name="' + name + '">Edit</button>' +
            '<button class="fdt-delete-token" data-name="' + name + '">Delete</button>' +
            '</div>' +
            '</div>';
    }

    function getTokenData($token) {
        var rangeText = $token.find('.fdt-token-range').text();
        var matches = rangeText.match(/\(([0-9.]+)rem → ([0-9.]+)rem\)/);

        if (matches) {
            return {
                min: parseFloat(matches[1]),
                max: parseFloat(matches[2])
            };
        }

        return { min: 0, max: 0 };
    }

    function updateAllTokenValues() {
        $('.fdt-token').each(function () {
            var $token = $(this);
            var tokenData = getTokenData($token);
            var fluidValue = calculateFluidValue(tokenData.min, tokenData.max);

            $token.find('.fdt-token-value').text('clamp(' + tokenData.min + 'rem, ' + fluidValue + ', ' + tokenData.max + 'rem)');
        });
    }

    function updateCurrentSettingsDisplay() {
        var rootSize = $('input[name="root_font_size"]:checked').val();
        var remSize = rootSize === '62.5%' ? '10px' : '16px';

        // Update root font size display
        $('.fdt-current-settings p').eq(0).html('<strong>Root Font Size:</strong> ' + rootSize + ' (1rem = ' + remSize + ')');

        // Get and update viewport range
        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_get_viewport',
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    var range = response.data.range;
                    var source = response.data.source;
                    $('.fdt-current-settings p').eq(1).html(
                        '<strong>Viewport Range:</strong> ' + range.min + 'px - ' + range.max + 'px ' +
                        '(' + (source === 'Elementor' ? 'from Elementor settings' : 'default') + ')'
                    );
                }
            }
        });
    }

    function checkEmptyTokenList() {
        var $container = $('#tokens-list');

        if ($container.find('.fdt-token').length === 0) {
            $container.html('<p class="fdt-no-tokens">No tokens yet. Add your first token above!</p>');
        }
    }

    function closeEditModal() {
        $('#edit-token-modal').fadeOut(300, function () {
            $('#edit-token-form')[0].reset();
            $('#edit-original-name').val('');
        });
    }

    function showNotice(message, type) {
        // Remove existing toasts
        $('.fdt-toast').remove();

        var toastClass = type === 'success' ? 'fdt-toast-success' : 'fdt-toast-error';
        var icon = type === 'success' ? '✓' : '✕';

        var toast = $(
            '<div class="fdt-toast ' + toastClass + '">' +
            '<span class="fdt-toast-icon">' + icon + '</span>' +
            '<span class="fdt-toast-message">' + message + '</span>' +
            '</div>'
        );

        $('body').append(toast);

        // Animate in (slide from left)
        setTimeout(function () {
            toast.addClass('fdt-toast-show');
        }, 10);

        // Auto-dismiss after 5 seconds (slide to right)
        setTimeout(function () {
            toast.removeClass('fdt-toast-show').addClass('fdt-toast-hide');
            setTimeout(function () {
                toast.remove();
            }, 400);
        }, 5000);
    }

    // Input sanitization
    $('#token-name, #edit-token-name').on('input', function () {
        var value = $(this).val();
        var sanitized = value.toLowerCase().replace(/[^a-z0-9-\s]/g, '');
        if (sanitized !== value) {
            $(this).val(sanitized);
        }
    });

    // Number input validation
    $('input[type="number"]').on('input', function () {
        var value = parseFloat($(this).val());
        if (value < 0) {
            $(this).val('');
        }
    });

    // Handle static token addition
    $('#add-static-token-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var name = $('#static-token-name').val().trim();
        var value = parseFloat($('#static-token-value').val());

        console.log('FDT: Adding static token:', { name, value });

        if (!validateStaticTokenInput(name, value)) {
            return;
        }

        $button.prop('disabled', true).text('Adding...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_add_static_token',
                name: name,
                value: value,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Add static token response:', response);
                $button.prop('disabled', false).text('Add Static Token');

                if (response.success) {
                    showNotice("Static token '--" + name + "' added successfully!", 'success');
                    $form[0].reset();
                    addStaticTokenToList(name, value);
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Add static token failed:', error);
                $button.prop('disabled', false).text('Add Static Token');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Handle static token editing
    $(document).on('click', '.fdt-edit-static-token', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $token = $button.closest('.fdt-static-token');
        var name = $button.data('name');
        var value = parseFloat($token.find('.fdt-token-value').text());

        // Populate edit form
        $('#edit-static-original-name').val(name);
        $('#edit-static-token-name').val(name);
        $('#edit-static-token-value').val(value);

        // Show modal
        $('#edit-static-token-modal').fadeIn(300);
        setTimeout(function () {
            $('#edit-static-token-name').focus().select();
        }, 350);
    });

    // Handle static token edit form submission
    $('#edit-static-token-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalName = $('#edit-static-original-name').val();
        var name = $('#edit-static-token-name').val().trim();
        var value = parseFloat($('#edit-static-token-value').val());

        console.log('FDT: Updating static token:', { originalName, name, value });

        if (!validateStaticTokenInput(name, value, originalName)) {
            return;
        }

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: fdt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fdt_edit_static_token',
                original_name: originalName,
                name: name,
                value: value,
                nonce: fdt_ajax.nonce
            },
            success: function (response) {
                console.log('FDT: Edit static token response:', response);
                $button.prop('disabled', false).text('Save Changes');

                if (response.success) {
                    showNotice("Static token '--fs-" + name + "' updated successfully!", 'success');
                    $('#edit-static-token-modal').fadeOut(300);
                    updateStaticTokenInList(originalName, name, value);
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('FDT: Edit static token failed:', error);
                $button.prop('disabled', false).text('Save Changes');
                showNotice('Network error: ' + error, 'error');
            }
        });
    });

    // Modal controls for static tokens
    $('#edit-static-token-modal .fdt-modal-close, #cancel-static-edit').on('click', function (e) {
        e.preventDefault();
        closeStaticEditModal();
    });

    $('#edit-static-token-modal').on('click', function (e) {
        if (e.target.id === 'edit-static-token-modal') {
            closeStaticEditModal();
        }
    });

    function closeStaticEditModal() {
        $('#edit-static-token-modal').fadeOut(300, function () {
            $('#edit-static-token-form')[0].reset();
            $('#edit-static-original-name').val('');
        });
    }

    function validateStaticTokenInput(name, value, originalName = '') {
        // Clean token name
        name = name.toLowerCase().replace(/[^a-z0-9-]/g, '');

        if (!name) {
            showNotice('Token name must contain letters or numbers.', 'error');
            return false;
        }

        if (isNaN(value) || value <= 0) {
            showNotice('Please enter a valid positive number.', 'error');
            return false;
        }

        // Check if token already exists (skip if it's the same name as original)
        if (name !== originalName && $('.fdt-static-token[data-name="' + name + '"]').length > 0) {
            showNotice('A static token with this name already exists.', 'error');
            return false;
        }

        return true;
    }

    function updateStaticTokenInList(originalName, newName, value) {
        var $oldToken = $('.fdt-static-token[data-name="' + originalName + '"]');
        var tokenHtml = createStaticTokenHtml(newName, value);
        var $newToken = $(tokenHtml);

        $oldToken.replaceWith($newToken);

        // Success animation
        $newToken.addClass('fdt-success');
        setTimeout(function () {
            $newToken.removeClass('fdt-success');
        }, 2000);
    }

    function createStaticTokenHtml(name, value) {
        return '<div class="fdt-token fdt-static-token" data-name="' + name + '">' +
            '<div class="fdt-token-info">' +
            '<div class="fdt-token-name-wrapper">' +
            '<strong>--fs-' + name + '</strong>' +
            '<button type="button" class="fdt-copy-token" data-token="var(--fs-' + name + ')" title="Copy token">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
            '</button>' +
            '</div>' +
            '<span class="fdt-token-value">' + value + 'rem</span>' +
            '</div>' +
            '<div class="fdt-token-actions">' +
            '<button class="fdt-edit-static-token" data-name="' + name + '">Edit</button>' +
            '<button class="fdt-delete-static-token" data-name="' + name + '">Delete</button>' +
            '</div>' +
            '</div>';
    }

    function checkEmptyStaticTokensList() {
        var $container = $('#static-tokens-list');

        if ($container.find('.fdt-static-token').length === 0) {
            $container.html('<p class="fdt-no-tokens">No static tokens yet. Add your first static token above!</p>');
        }
    }

    // ===================================
    // Import/Export Functionality
    // ===================================

    // Handle Export button click
    $('#fdt-export-tokens').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true).text('Exporting...');

        // Gather all tokens from the page
        var tokens = {};
        var staticTokens = {};

        // Get fluid tokens
        $('.fdt-token:not(.fdt-static-token)').each(function () {
            var $token = $(this);
            var name = $token.data('name');
            var tokenData = getTokenData($token);
            if (name && tokenData.min && tokenData.max) {
                tokens[name] = {
                    min: tokenData.min,
                    max: tokenData.max
                };
            }
        });

        // Get static tokens
        $('.fdt-static-token').each(function () {
            var $token = $(this);
            var name = $token.data('name');
            var value = parseFloat($token.find('.fdt-token-value').text());
            if (name && !isNaN(value)) {
                staticTokens[name] = value;
            }
        });

        // Get root font size setting
        var rootFontSize = $('input[name="root_font_size"]:checked').val() || '62.5%';

        // Create export object
        var exportData = {
            version: '1.0',
            exported_at: new Date().toISOString(),
            settings: {
                root_font_size: rootFontSize
            },
            tokens: tokens,
            static_tokens: staticTokens
        };

        // Create and download JSON file
        var dataStr = JSON.stringify(exportData, null, 2);
        var blob = new Blob([dataStr], { type: 'application/json' });
        var url = URL.createObjectURL(blob);

        var a = document.createElement('a');
        a.href = url;
        a.download = 'fluid-design-tokens-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        $button.prop('disabled', false).text('Export');
        showNotice('Tokens exported successfully!', 'success');
    });

    // Handle Import button click
    $('#fdt-import-tokens').on('click', function () {
        $('#fdt-import-file').trigger('click');
    });

    // Store import data globally for use in confirm handler
    var pendingImportData = null;

    // Handle file selection for import
    $('#fdt-import-file').on('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (event) {
            try {
                var importData = JSON.parse(event.target.result);

                // Validate import data structure
                if (!importData.tokens && !importData.static_tokens) {
                    showNotice('Invalid import file: No tokens found.', 'error');
                    return;
                }

                // Count tokens
                var tokenCount = Object.keys(importData.tokens || {}).length;
                var staticCount = Object.keys(importData.static_tokens || {}).length;

                if (tokenCount === 0 && staticCount === 0) {
                    showNotice('No tokens to import.', 'error');
                    return;
                }

                // Store the import data for later
                pendingImportData = importData;

                // Update modal message
                var message = 'Import ' + tokenCount + ' fluid token(s) and ' + staticCount + ' static token(s)?';
                $('#import-confirm-message').text(message);

                // Show custom modal
                $('#import-confirm-modal').fadeIn(300);


            } catch (error) {
                console.error('FDT: Import parse error:', error);
                showNotice('Error parsing import file: ' + error.message, 'error');
            }
        };

        reader.onerror = function () {
            showNotice('Error reading file.', 'error');
        };

        reader.readAsText(file);

        // Reset file input
        $(this).val('');
    });

    // Import tokens to server via AJAX
    function importTokensToServer(importData) {
        var importedCount = 0;
        var skippedCount = 0;
        var promises = [];

        // Import fluid tokens
        if (importData.tokens) {
            $.each(importData.tokens, function (name, token) {
                // Check if token already exists
                if ($('.fdt-token[data-name="' + name + '"]').length > 0) {
                    skippedCount++;
                    return true; // continue
                }

                var promise = $.ajax({
                    url: fdt_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fdt_add_token',
                        name: name,
                        min: token.min,
                        max: token.max,
                        nonce: fdt_ajax.nonce
                    }
                }).then(function (response) {
                    if (response.success) {
                        importedCount++;
                        addTokenToList(name, token.min, token.max);
                    } else {
                        skippedCount++;
                    }
                }).catch(function () {
                    skippedCount++;
                });

                promises.push(promise);
            });
        }

        // Import static tokens
        if (importData.static_tokens) {
            $.each(importData.static_tokens, function (name, value) {
                // Check if token already exists
                if ($('.fdt-static-token[data-name="' + name + '"]').length > 0) {
                    skippedCount++;
                    return true; // continue
                }

                var promise = $.ajax({
                    url: fdt_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fdt_add_static_token',
                        name: name,
                        value: value,
                        nonce: fdt_ajax.nonce
                    }
                }).then(function (response) {
                    if (response.success) {
                        importedCount++;
                        addStaticTokenToList(name, value);
                    } else {
                        skippedCount++;
                    }
                }).catch(function () {
                    skippedCount++;
                });

                promises.push(promise);
            });
        }

        // Wait for all imports to complete
        $.when.apply($, promises).always(function () {
            var message = 'Import complete: ' + importedCount + ' token(s) imported';
            if (skippedCount > 0) {
                message += ', ' + skippedCount + ' skipped (already exist or failed)';
            }
            showNotice(message, importedCount > 0 ? 'success' : 'error');
        });
    }

    // Add static token to list (matching the existing createStaticTokenHtml function)
    function addStaticTokenToList(name, value) {
        var $container = $('#static-tokens-list');

        // Remove "no tokens" message
        $container.find('.fdt-no-tokens').remove();

        var tokenHtml = createStaticTokenHtml(name, value);
        var $newToken = $(tokenHtml);
        $container.prepend($newToken);

        // Success animation
        $newToken.addClass('fdt-success');
        setTimeout(function () {
            $newToken.removeClass('fdt-success');
        }, 2000);
    }

    // Handle Copy Token functionality
    $(document).on('click', '.fdt-copy-token', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $button = $(this);
        var tokenValue = $button.data('token');

        // Copy to clipboard
        navigator.clipboard.writeText(tokenValue).then(function () {
            $button.addClass('copied');
            showNotice('Copied: ' + tokenValue, 'success');

            setTimeout(function () {
                $button.removeClass('copied');
            }, 1500);
        }).catch(function () {
            showNotice('Failed to copy token', 'error');
        });
    });

    // Handle Search functionality
    var searchTimeout;
    $('#fdt-search').on('input', function () {
        var searchTerm = $(this).val().toLowerCase().trim();
        var $clearButton = $('#fdt-clear-search');

        clearTimeout(searchTimeout);

        if (searchTerm.length > 0) {
            $clearButton.addClass('visible');
        } else {
            $clearButton.removeClass('visible');
        }

        searchTimeout = setTimeout(function () {
            // Filter fluid tokens
            $('.fdt-token:not(.fdt-static-token)').each(function () {
                var $token = $(this);
                var name = $token.data('name').toLowerCase();
                if (searchTerm === '' || name.indexOf(searchTerm) !== -1) {
                    $token.show();
                } else {
                    $token.hide();
                }
            });

            // Filter static tokens
            $('.fdt-static-token').each(function () {
                var $token = $(this);
                var name = $token.data('name').toLowerCase();
                if (searchTerm === '' || name.indexOf(searchTerm) !== -1) {
                    $token.show();
                } else {
                    $token.hide();
                }
            });
        }, 200);
    });

    // Clear search
    $('#fdt-clear-search').on('click', function () {
        $('#fdt-search').val('').trigger('input');
        $(this).removeClass('visible');
    });

    // ===================================
    // Import Modal Handlers
    // ===================================

    // Handle confirm import button click
    $('#confirm-import').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true).text('Importing...');

        // Hide modal
        $('#import-confirm-modal').fadeOut(300);

        // Import the tokens
        if (pendingImportData) {
            importTokensToServer(pendingImportData);
        }

        // Reset button and pending data
        setTimeout(function () {
            $button.prop('disabled', false).text('Import');
            pendingImportData = null;
        }, 500);
    });

    // Handle cancel import button click
    $('#cancel-import').on('click', function () {
        $('#import-confirm-modal').fadeOut(300);
        pendingImportData = null;
    });

    // Close import modal when clicking outside or on close button
    $('#import-confirm-modal .fdt-modal-close').on('click', function () {
        $('#import-confirm-modal').fadeOut(300);
        pendingImportData = null;
    });

    $('#import-confirm-modal').on('click', function (e) {
        if (e.target.id === 'import-confirm-modal') {
            $(this).fadeOut(300);
            pendingImportData = null;
        }
    });

});