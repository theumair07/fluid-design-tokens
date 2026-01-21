<?php
/**
 * Plugin Name: Fluid Design Tokens
 * Description: A simple utility plugin for managing fluid design tokens using CSS custom properties with automatic viewport detection.
 * Version: 1.0.0
 * Author: Waqas Ahmed, Umair Khan
 * Author URI: https://github.com/theumair07/fluid-design-tokens
 * Contributors: theumair07
 * License: GPL v2 or later
 * Text Domain: fluid-design-tokens
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FDT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FDT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FDT_VERSION', '1.0.0');

class FluidDesignTokens {
    
    private $option_name = 'fluid_design_tokens_settings';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Frontend hooks
        add_action('wp_head', array($this, 'output_css_variables'));
        
        // AJAX hooks
        add_action('wp_ajax_fdt_add_token', array($this, 'ajax_add_token'));
        add_action('wp_ajax_fdt_edit_token', array($this, 'ajax_edit_token'));
        add_action('wp_ajax_fdt_delete_token', array($this, 'ajax_delete_token'));
        add_action('wp_ajax_fdt_update_settings', array($this, 'ajax_update_settings'));
        add_action('wp_ajax_fdt_get_viewport', array($this, 'ajax_get_viewport'));
        add_action('wp_ajax_fdt_search_tokens', array($this, 'ajax_search_tokens'));
        
        // Static token AJAX hooks
        add_action('wp_ajax_fdt_add_static_token', array($this, 'ajax_add_static_token'));
        add_action('wp_ajax_fdt_edit_static_token', array($this, 'ajax_edit_static_token'));
        add_action('wp_ajax_fdt_delete_static_token', array($this, 'ajax_delete_static_token'));
        
        // Import/Export AJAX hooks
        add_action('wp_ajax_fdt_export_tokens', array($this, 'ajax_export_tokens'));
        add_action('wp_ajax_fdt_import_tokens', array($this, 'ajax_import_tokens'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Fluid Design Tokens',
            'Design Tokens',
            'manage_options',
            'fluid-design-tokens',
            array($this, 'admin_page'),
            'dashicons-superhero-alt',
            80
        );
    }
    
    public function admin_init() {
        register_setting('fluid_design_tokens_group', $this->option_name, array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Initialize default settings
        $existing_settings = get_option($this->option_name, false);
        $default_settings = array(
            'root_font_size' => '62.5%',
            'tokens' => array(),
            'static_tokens' => array()
        );
        
        if (false === $existing_settings) {
            add_option($this->option_name, $default_settings);
        } else {
            // Merge existing settings with defaults
            $merged_settings = array_merge($default_settings, $existing_settings);
            update_option($this->option_name, $merged_settings);
        }
    }
    
    /**
     * Sanitize plugin settings
     *
     * @param array $input The settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize root font size
        $sanitized['root_font_size'] = isset($input['root_font_size']) && in_array($input['root_font_size'], array('100%', '62.5%')) 
            ? $input['root_font_size'] 
            : '62.5%';
        
        // Sanitize tokens
        $sanitized['tokens'] = array();
        if (!empty($input['tokens']) && is_array($input['tokens'])) {
            foreach ($input['tokens'] as $name => $token) {
                if (is_array($token) && isset($token['min']) && isset($token['max'])) {
                    $sanitized_name = sanitize_text_field($name);
                    $sanitized['tokens'][$sanitized_name] = array(
                        'min' => floatval($token['min']),
                        'max' => floatval($token['max'])
                    );
                }
            }
        }
        
        // Sanitize static tokens
        $sanitized['static_tokens'] = array();
        if (!empty($input['static_tokens']) && is_array($input['static_tokens'])) {
            foreach ($input['static_tokens'] as $name => $value) {
                $sanitized_name = sanitize_text_field($name);
                $sanitized['static_tokens'][$sanitized_name] = floatval($value);
            }
        }
        
        return $sanitized;
    }
    
    public function admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_fluid-design-tokens') {
            return;
        }
        
        // Enqueue scripts and styles
        wp_enqueue_script('fdt-admin', FDT_PLUGIN_URL . 'assets/admin.js', array('jquery'), FDT_VERSION, true);
        wp_enqueue_style('fdt-admin', FDT_PLUGIN_URL . 'assets/admin.css', array(), FDT_VERSION);
        
        // Localize script with AJAX data
        wp_localize_script('fdt-admin', 'fdt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fdt_nonce')
        ));
    }
    
    private function get_viewport_range() {
        // Try to get Elementor content width settings
        $elementor_settings = $this->get_elementor_settings();
        
        if ($elementor_settings) {
            return array(
                'min' => $elementor_settings['mobile'],
                'max' => $elementor_settings['desktop']
            );
        }
        
        // Fallback to default responsive breakpoints
        return array(
            'min' => 320,  // Mobile
            'max' => 1200  // Desktop
        );
    }
    
    private function get_elementor_settings() {
        // Check if Elementor is active
        if (!defined('ELEMENTOR_VERSION')) {
            return false;
        }
        
        // Get Elementor's active kit settings
        $kit_id = get_option('elementor_active_kit');
        if (!$kit_id) {
            return false;
        }
        
        // Get kit settings
        $kit_settings = get_post_meta($kit_id, '_elementor_page_settings', true);
        
        if (empty($kit_settings)) {
            return false;
        }
        
        // Extract viewport settings with fallbacks
        $settings = array();
        
        // Mobile breakpoint (default 767px if not set)
        $settings['mobile'] = isset($kit_settings['viewport_mobile']) ? 
            intval($kit_settings['viewport_mobile']) : 
            (isset($kit_settings['system_colors']) ? 767 : 320);
        
        // Desktop content width (default from container settings)
        if (isset($kit_settings['container_width']['size'])) {
            $settings['desktop'] = intval($kit_settings['container_width']['size']);
        } elseif (isset($kit_settings['content_width']['size'])) {
            $settings['desktop'] = intval($kit_settings['content_width']['size']);
        } else {
            // Try to get from site settings
            $settings['desktop'] = $this->get_elementor_content_width_fallback();
        }
        
        // Validate settings
        if ($settings['desktop'] <= $settings['mobile']) {
            return false;
        }
        
        return $settings;
    }
    
    private function get_elementor_content_width_fallback() {
        // First try to get the container width directly
        $container_width = get_option('elementor_container_width');
        if ($container_width && is_numeric($container_width) && $container_width > 800) {
            return intval($container_width);
        }
        
        // Try other Elementor content width sources
        $sources = array(
            'elementor_content_width', 
            'elementor_global_content_width'
        );
        
        foreach ($sources as $source) {
            $width = get_option($source);
            if ($width && is_numeric($width) && $width > 800) {
                return intval($width);
            }
        }
        
        // Ultimate fallback to 1140
        return 1140;
    }
    
    private function get_viewport_info() {
        $viewport = $this->get_viewport_range();
        $source = defined('ELEMENTOR_VERSION') && $this->get_elementor_settings() ? 'Elementor' : 'Default';
        
        return array(
            'range' => $viewport,
            'source' => $source
        );
    }
    
    private function calculate_fluid_value($min_rem, $max_rem) {
        $viewport = $this->get_viewport_range();
        $min_vw = $viewport['min'];
        $max_vw = $viewport['max'];
        
        // Get current root font size setting
        $settings = get_option($this->option_name);
        $base_font_size = ($settings['root_font_size'] === '62.5%') ? 10 : 16; // px per rem
        
        // Convert viewport widths to rem for calculations
        $min_vw_rem = $min_vw / $base_font_size;
        $max_vw_rem = $max_vw / $base_font_size;
        
        // Calculate slope: (max_size - min_size) / (max_viewport - min_viewport)
        $slope = ($max_rem - $min_rem) / ($max_vw_rem - $min_vw_rem);
        
        // Calculate intercept: min_size - (slope * min_viewport)
        $intercept = $min_rem - ($slope * $min_vw_rem);
        
        // Convert slope to vw units
        $slope_vw = $slope * 100;
        
        // Round to 3 decimal places
        $slope_vw = round($slope_vw, 3);
        $intercept = round($intercept, 3);
        
        // Format the output
        if ($intercept >= 0) {
            $fluid_value = $slope_vw . 'vw + ' . $intercept . 'rem';
        } else {
            $fluid_value = $slope_vw . 'vw - ' . abs($intercept) . 'rem';
        }
        
        return $fluid_value;
    }
    
    public function admin_page() {
        $settings = get_option($this->option_name);
        $viewport_info = $this->get_viewport_info();
        $viewport = $viewport_info['range'];
        $source = $viewport_info['source'];
        
        // Ensure settings exist
        $settings = wp_parse_args($settings, array(
            'root_font_size' => '62.5%',
            'tokens' => array(),
            'static_tokens' => array()
        ));
        ?>
<div class="wrap fdt-admin">
    <div class="fdt-container">
        <!-- Header Section -->
        <div class="fdt-section fdt-header-section">
            <div class="fdt-header-icon">
                <span class="dashicons dashicons-superhero-alt"></span>
            </div>
            <div class="fdt-header-content">
                <h1>Fluid Design Tokens</h1>
                <p>Create fluid design tokens that automatically scale between mobile
                    (<?php echo esc_html($viewport['min']); ?>px)
                    and desktop (<?php echo esc_html($viewport['max']); ?>px) viewports.</p>

                <?php if ($source === 'Elementor'): ?>
                <p class="fdt-header-notice">
                    <strong>🎯 Elementor Integration Active:</strong> Using viewport settings from your Elementor theme
                    (Mobile:
                    <?php echo esc_html($viewport['min']); ?>px, Desktop: <?php echo esc_html($viewport['max']); ?>px)
                </p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Root Font Size and Search Sections Row -->
        <div class="fdt-sections-row">
            <!-- Settings Section -->
            <div class="fdt-section">
                <h2>Root Font Size</h2>
                <div class="fdt-section-row">
                    <div class="fdt-root-controls">
                        <label>
                            <input type="radio" name="root_font_size" value="100%"
                                <?php checked($settings['root_font_size'], '100%'); ?>>
                            <span>100% (1rem = 16px)</span>
                        </label>
                        <label>
                            <input type="radio" name="root_font_size" value="62.5%"
                                <?php checked($settings['root_font_size'], '62.5%'); ?>>
                            <span>62.5% (1rem = 10px)</span>
                        </label>
                    </div>
                    <button type="button" id="save-settings" class="button button-primary">Save Settings</button>
                </div>
            </div>

            <!-- Search & Import/Export Section -->
            <div class="fdt-section fdt-section-split">
                <div class="fdt-section-column">
                    <h3>Search Tokens</h3>
                    <div class="fdt-search-container">
                        <input type="text" id="fdt-search" class="fdt-search-input"
                            placeholder="Search tokens by name..." />
                        <button type="button" id="fdt-clear-search" class="fdt-clear-search">Clear</button>
                    </div>
                </div>
                <div class="fdt-section-column">
                    <h3>Import & Export Tokens</h3>
                    <div class="fdt-import-export">
                        <button type="button" id="fdt-export-tokens" class="button button-primary">Export</button>
                        <button type="button" id="fdt-import-tokens" class="button button-primary">Import</button>
                        <input type="file" id="fdt-import-file" accept=".json" style="display: none;" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Tokens Section -->
        <div class="fdt-section">
            <h2>Design Tokens</h2>
            <p>Create fluid tokens for typography, spacing, or any CSS property that needs responsive scaling.</p>

            <div class="fdt-add-token">
                <h3>Add New Token</h3>
                <form id="add-token-form">
                    <div class="fdt-form-row">
                        <input type="text" id="token-name" placeholder="Token name (e.g., h1, section-padding)"
                            required>
                        <input type="number" id="token-min" placeholder="Min size (rem)" step="0.1" min="0.1" required>
                        <input type="number" id="token-max" placeholder="Max size (rem)" step="0.1" min="0.1" required>
                        <button type="submit" class="button button-primary">Add Token</button>
                    </div>
                </form>
            </div>

            <div class="fdt-tokens-list" id="tokens-list">
                <?php $this->render_tokens($settings); ?>
            </div>
        </div>

        <!-- Static Tokens Section -->
        <div class="fdt-section">
            <h2>Static Tokens</h2>
            <p>Create static tokens for fixed values like borders, fixed spacing, or any CSS property that needs a
                constant value.</p>

            <div class="fdt-add-static-token">
                <h3>Add New Static Token</h3>
                <form id="add-static-token-form">
                    <div class="fdt-form-row">
                        <input type="text" id="static-token-name"
                            placeholder="Token name (e.g., fs-border-width, fs-gap)" required>
                        <input type="number" id="static-token-value" placeholder="Value (rem)" step="0.1" min="0.1"
                            required>
                        <button type="submit" class="button button-primary">Add Static Token</button>
                    </div>
                </form>
            </div>

            <div class="fdt-static-tokens-list" id="static-tokens-list">
                <?php $this->render_static_tokens($settings); ?>
            </div>
        </div>

        <!-- Usage Examples Section -->
        <div class="fdt-section">
            <h2>Usage Examples</h2>
            <div class="fdt-examples">
                <div class="fdt-example">
                    <h4>Typography:</h4>
                    <code>h1 { font-size: var(--h1); }</code>
                </div>
                <div class="fdt-example">
                    <h4>Spacing:</h4>
                    <code>.section { padding: var(--section-padding) 0; }</code>
                </div>
                <div class="fdt-example">
                    <h4>Multiple Properties:</h4>
                    <code>.card { 
    padding: var(--card-padding);
    gap: var(--card-gap);
    font-size: var(--body-text);
}</code>
                </div>
            </div>

            <div class="fdt-current-settings">
                <h4>Current Settings:</h4>
                <p><strong>Root Font Size:</strong> <?php echo esc_html($settings['root_font_size']); ?>
                    (1rem = <?php echo $settings['root_font_size'] === '62.5%' ? '10px' : '16px'; ?>)</p>
                <p><strong>Viewport Range:</strong> <?php echo esc_html($viewport['min']); ?>px -
                    <?php echo esc_html($viewport['max']); ?>px
                    (<?php echo $source === 'Elementor' ? 'from Elementor settings' : 'default'; ?>)</p>
                <?php if ($source === 'Elementor'): ?>
                <p><em>💡 Tip: Changes to your Elementor content width will automatically update all fluid tokens.</em>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Edit Token Modal -->
<div id="edit-token-modal" class="fdt-modal">
    <div class="fdt-modal-content">
        <div class="fdt-modal-header">
            <h3>Edit Token</h3>
            <button type="button" class="fdt-modal-close">&times;</button>
        </div>
        <form id="edit-token-form">
            <input type="hidden" id="edit-original-name">
            <div class="fdt-form-row">
                <label for="edit-token-name">Token Name:</label>
                <input type="text" id="edit-token-name" required>
            </div>
            <div class="fdt-form-row">
                <label for="edit-token-min">Min Size (rem):</label>
                <input type="number" id="edit-token-min" step="0.1" min="0.1" required>
            </div>
            <div class="fdt-form-row">
                <label for="edit-token-max">Max Size (rem):</label>
                <input type="number" id="edit-token-max" step="0.1" min="0.1" required>
            </div>
            <div class="fdt-modal-actions">
                <button type="button" class="button" id="cancel-edit">Cancel</button>
                <button type="submit" class="button button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Static Token Modal -->
<div id="edit-static-token-modal" class="fdt-modal">
    <div class="fdt-modal-content">
        <div class="fdt-modal-header">
            <h3>Edit Static Token</h3>
            <button type="button" class="fdt-modal-close">&times;</button>
        </div>
        <form id="edit-static-token-form">
            <input type="hidden" id="edit-static-original-name">
            <div class="fdt-form-row">
                <label for="edit-static-token-name">Token Name:</label>
                <input type="text" id="edit-static-token-name" required>
            </div>
            <div class="fdt-form-row">
                <label for="edit-static-token-value">Value (rem):</label>
                <input type="number" id="edit-static-token-value" step="0.1" min="0.1" required>
            </div>
            <div class="fdt-modal-actions">
                <button type="button" class="button" id="cancel-static-edit">Cancel</button>
                <button type="submit" class="button button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Token Modal -->
<div id="delete-token-modal" class="fdt-modal">
    <div class="fdt-modal-content fdt-delete-modal">
        <div class="fdt-modal-header">
            <h3>Delete Token</h3>
            <button type="button" class="fdt-modal-close">&times;</button>
        </div>
        <div class="fdt-modal-body">
            <p id="delete-token-message" class="fdt-delete-message">Are you sure you want to delete this token?</p>
            <p class="fdt-delete-warning">This action cannot be undone.</p>
        </div>
        <div class="fdt-modal-actions">
            <button type="button" class="button" id="cancel-delete">Cancel</button>
            <button type="button" class="button fdt-btn-danger fdt-confirm-delete">Delete</button>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div id="import-confirm-modal" class="fdt-modal">
    <div class="fdt-modal-content">
        <div class="fdt-modal-header">
            <h3>Import Tokens</h3>
            <button type="button" class="fdt-modal-close">&times;</button>
        </div>
        <div class="fdt-modal-body">
            <p id="import-confirm-message" class="fdt-import-message">Import tokens?</p>
            <p class="fdt-import-warning">This will add new tokens. Existing tokens with the same names will be skipped.
            </p>
        </div>
        <div class="fdt-modal-actions">
            <button type="button" class="button" id="cancel-import">Cancel</button>
            <button type="button" class="button button-primary" id="confirm-import">Import</button>
        </div>
    </div>
</div>

<?php
    }
    
    private function render_tokens($settings) {
        if (empty($settings['tokens'])) {
            echo '<p class="fdt-no-tokens">No tokens yet. Add your first token above!</p>';
            return;
        }
        
        foreach ($settings['tokens'] as $name => $token) {
            $fluid_value = $this->calculate_fluid_value($token['min'], $token['max']);
            
            echo '<div class="fdt-token" data-name="' . esc_attr($name) . '">';
            echo '<div class="fdt-token-info">';
            echo '<div class="fdt-token-name-wrapper">';
            echo '<strong>--' . esc_html($name) . '</strong>';
            echo '<button type="button" class="fdt-copy-token" data-token="var(--' . esc_attr($name) . ')" title="Copy token">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            echo '</button>';
            echo '</div>';
            echo '<span class="fdt-token-value">clamp(' . esc_html($token['min']) . 'rem, ' . esc_html($fluid_value) . ', ' . esc_html($token['max']) . 'rem)</span>';
            echo '<span class="fdt-token-range">(' . esc_html($token['min']) . 'rem → ' . esc_html($token['max']) . 'rem)</span>';
            echo '</div>';
            echo '<div class="fdt-token-actions">';
            echo '<button class="fdt-edit-token" data-name="' . esc_attr($name) . '">Edit</button>';
            echo '<button class="fdt-delete-token" data-name="' . esc_attr($name) . '">Delete</button>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    private function render_static_tokens($settings) {
        if (empty($settings['static_tokens'])) {
            echo '<p class="fdt-no-tokens">No static tokens yet. Add your first static token above!</p>';
            return;
        }
        
        foreach ($settings['static_tokens'] as $name => $value) {
            echo '<div class="fdt-token fdt-static-token" data-name="' . esc_attr($name) . '">';
            echo '<div class="fdt-token-info">';
            echo '<div class="fdt-token-name-wrapper">';
            echo '<strong>--fs-' . esc_html($name) . '</strong>';
            echo '<button type="button" class="fdt-copy-token" data-token="var(--fs-' . esc_attr($name) . ')" title="Copy token">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            echo '</button>';
            echo '</div>';
            echo '<span class="fdt-token-value">' . esc_html($value) . 'rem</span>';
            echo '</div>';
            echo '<div class="fdt-token-actions">';
            echo '<button class="fdt-edit-static-token" data-name="' . esc_attr($name) . '">Edit</button>';
            echo '<button class="fdt-delete-static-token" data-name="' . esc_attr($name) . '">Delete</button>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Analytics page
     */
    public function analytics_page() {
        $settings = get_option($this->option_name);
        ?>
<div class="wrap fdt-admin">
    <h1>Design Tokens</h1>
    <p>Manage your fluid and static design tokens.</p>



    <!-- Fluid Tokens List -->
    <div class="fdt-section">
        <h2>Fluid Tokens</h2>
        <div class="fdt-tokens-list" id="tokens-list">
            <?php $this->render_tokens($settings); ?>
        </div>
    </div>

    <!-- Static Tokens List -->
    <div class="fdt-section">
        <h2>Static Tokens</h2>
        <div class="fdt-tokens-list" id="static-tokens-list">
            <?php $this->render_static_tokens($settings); ?>
        </div>
    </div>
</div>
<?php
    }
    
    public function output_css_variables() {
        $settings = get_option($this->option_name);
        
        if (empty($settings['tokens']) && empty($settings['static_tokens'])) {
            return;
        }
        
        echo '<style id="fdt-css-variables">';
        // Set root font size
        echo 'html { font-size: ' . esc_attr($settings['root_font_size']) . '; }';
        
        // Output CSS custom properties
        echo ':root {';
        // Output fluid tokens
        foreach ($settings['tokens'] as $name => $token) {
            $fluid_value = $this->calculate_fluid_value($token['min'], $token['max']);
            echo '--' . esc_attr($name) . ': clamp(' . 
                 esc_attr($token['min']) . 'rem, ' . 
                 esc_attr($fluid_value) . ', ' . 
                 esc_attr($token['max']) . 'rem);';
        }
        // Output static tokens with fs- prefix
        if (!empty($settings['static_tokens'])) {
            foreach ($settings['static_tokens'] as $name => $value) {
                echo '--fs-' . esc_attr($name) . ': ' . esc_attr($value) . 'rem;';
            }
        }
        echo '}';
        echo '</style>';
    }
    
    public function ajax_add_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['name']) || !isset($_POST['min']) || !isset($_POST['max'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        $min = floatval(wp_unslash($_POST['min']));
        $max = floatval(wp_unslash($_POST['max']));
        
        // Validation
        if (empty($name) || $min <= 0 || $max <= 0 || $max <= $min) {
            wp_send_json_error('Invalid token data');
        }
        
        $settings = get_option($this->option_name);
        
        // Check if token already exists
        if (isset($settings['tokens'][$name])) {
            wp_send_json_error('Token already exists');
        }
        
        // Add token
        $settings['tokens'][$name] = array('min' => $min, 'max' => $max);
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success(array(
                'message' => 'Token added successfully',
                'token' => array('name' => $name, 'min' => $min, 'max' => $max)
            ));
        } else {
            wp_send_json_error('Failed to save token');
        }
    }
    
    public function ajax_edit_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['original_name']) || !isset($_POST['name']) || !isset($_POST['min']) || !isset($_POST['max'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $original_name = sanitize_text_field(wp_unslash($_POST['original_name']));
        $new_name = sanitize_text_field(wp_unslash($_POST['name']));
        $min = floatval(wp_unslash($_POST['min']));
        $max = floatval(wp_unslash($_POST['max']));
        
        // Validation
        if (empty($new_name) || $min <= 0 || $max <= 0 || $max <= $min) {
            wp_send_json_error('Invalid token data');
        }
        
        $settings = get_option($this->option_name);
        
        // Check if original token exists
        if (!isset($settings['tokens'][$original_name])) {
            wp_send_json_error('Token not found');
        }
        
        // Remove old token if name changed
        if ($original_name !== $new_name) {
            unset($settings['tokens'][$original_name]);
        }
        
        // Update token
        $settings['tokens'][$new_name] = array('min' => $min, 'max' => $max);
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success(array(
                'message' => 'Token updated successfully',
                'token' => array('name' => $new_name, 'min' => $min, 'max' => $max)
            ));
        } else {
            wp_send_json_error('Failed to update token');
        }
    }
    
    public function ajax_delete_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['name'])) {
            wp_send_json_error('Missing token name');
        }
        
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        
        if (empty($name)) {
            wp_send_json_error('Invalid token name');
        }
        
        $settings = get_option($this->option_name);
        
        if (!isset($settings['tokens'][$name])) {
            wp_send_json_error('Token not found');
        }
        
        // Delete token
        unset($settings['tokens'][$name]);
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success('Token deleted successfully');
        } else {
            wp_send_json_error('Failed to delete token');
        }
    }
    
    public function ajax_get_viewport() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $viewport_info = $this->get_viewport_info();
        wp_send_json_success($viewport_info);
    }
    
    public function ajax_update_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['root_font_size'])) {
            wp_send_json_error('Missing root font size');
        }
        
        $root_size = sanitize_text_field(wp_unslash($_POST['root_font_size']));
        
        if (!in_array($root_size, array('100%', '62.5%'))) {
            wp_send_json_error('Invalid root font size');
        }
        
        $settings = get_option($this->option_name);
        $settings['root_font_size'] = $root_size;
        
        if (update_option($this->option_name, $settings)) {
            // Return updated viewport info as well
            $viewport_info = $this->get_viewport_info();
            wp_send_json_success(array(
                'message' => 'Settings updated successfully',
                'viewport' => $viewport_info
            ));
        } else {
            wp_send_json_error('Failed to update settings');
        }
    }
    
    public function ajax_add_static_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['name']) || !isset($_POST['value'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        $value = floatval(wp_unslash($_POST['value']));
        
        // Validation
        if (empty($name) || $value <= 0) {
            wp_send_json_error('Invalid token data');
        }
        
        $settings = get_option($this->option_name);
        
        // Check if token already exists
        if (isset($settings['static_tokens'][$name])) {
            wp_send_json_error('Token already exists');
        }
        
        // Add token
        $settings['static_tokens'][$name] = $value;
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success(array(
                'message' => 'Static token added successfully',
                'token' => array('name' => $name, 'value' => $value)
            ));
        } else {
            wp_send_json_error('Failed to save token');
        }
    }
    
    public function ajax_edit_static_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['name']) || !isset($_POST['value'])) {
            wp_send_json_error('Missing required fields');
        }
        
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        $value = floatval(wp_unslash($_POST['value']));
        
        // Validation
        if (empty($name) || $value <= 0) {
            wp_send_json_error('Invalid token data');
        }
        
        $settings = get_option($this->option_name);
        
        // Check if token exists
        if (!isset($settings['static_tokens'][$name])) {
            wp_send_json_error('Token not found');
        }
        
        // Update token
        $settings['static_tokens'][$name] = $value;
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success(array(
                'message' => 'Static token updated successfully',
                'token' => array('name' => $name, 'value' => $value)
            ));
        } else {
            wp_send_json_error('Failed to update token');
        }
    }
    
    public function ajax_delete_static_token() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['name'])) {
            wp_send_json_error('Missing token name');
        }
        
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        
        if (empty($name)) {
            wp_send_json_error('Invalid token name');
        }
        
        $settings = get_option($this->option_name);
        
        if (!isset($settings['static_tokens'][$name])) {
            wp_send_json_error('Token not found');
        }
        
        // Delete token
        unset($settings['static_tokens'][$name]);
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success('Static token deleted successfully');
        } else {
            wp_send_json_error('Failed to delete token');
        }
    }

    public function ajax_search_tokens() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['search'])) {
            wp_send_json_error('Missing search term');
        }
        
        $search_term = sanitize_text_field(wp_unslash($_POST['search']));
        $settings = get_option($this->option_name);
        
        $results = array(
            'fluid' => array(),
            'static' => array()
        );
        
        // Search fluid tokens
        if (!empty($settings['tokens'])) {
            foreach ($settings['tokens'] as $name => $token) {
                if (empty($search_term) || stripos($name, $search_term) !== false) {
                    $results['fluid'][$name] = $token;
                }
            }
        }
        
        // Search static tokens
        if (!empty($settings['static_tokens'])) {
            foreach ($settings['static_tokens'] as $name => $value) {
                if (empty($search_term) || stripos($name, $search_term) !== false) {
                    $results['static'][$name] = $value;
                }
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Export tokens as JSON
     */
    public function ajax_export_tokens() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $settings = get_option($this->option_name);
        
        $export_data = array(
            'version' => FDT_VERSION,
            'root_font_size' => isset($settings['root_font_size']) ? $settings['root_font_size'] : '62.5%',
            'tokens' => isset($settings['tokens']) ? $settings['tokens'] : array(),
            'static_tokens' => isset($settings['static_tokens']) ? $settings['static_tokens'] : array()
        );
        
        wp_send_json_success($export_data);
    }
    
    /**
     * Import tokens from JSON
     */
    public function ajax_import_tokens() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fdt_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['tokens'])) {
            wp_send_json_error('No data provided');
        }
        
        $json_data = sanitize_text_field(wp_unslash($_POST['tokens']));
        $import_data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON data');
        }
        
        $settings = get_option($this->option_name);
        
        // Import root font size if provided
        if (isset($import_data['root_font_size']) && in_array($import_data['root_font_size'], array('100%', '62.5%'))) {
            $settings['root_font_size'] = $import_data['root_font_size'];
        }
        
        // Import fluid tokens
        if (isset($import_data['tokens']) && is_array($import_data['tokens'])) {
            foreach ($import_data['tokens'] as $name => $token) {
                if (is_array($token) && isset($token['min']) && isset($token['max'])) {
                    $sanitized_name = sanitize_text_field($name);
                    $settings['tokens'][$sanitized_name] = array(
                        'min' => floatval($token['min']),
                        'max' => floatval($token['max'])
                    );
                }
            }
        }
        
        // Import static tokens
        if (isset($import_data['static_tokens']) && is_array($import_data['static_tokens'])) {
            foreach ($import_data['static_tokens'] as $name => $value) {
                $sanitized_name = sanitize_text_field($name);
                $settings['static_tokens'][$sanitized_name] = floatval($value);
            }
        }
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success('Tokens imported successfully');
        } else {
            wp_send_json_error('Failed to import tokens');
        }
    }
}

// Initialize the plugin
new FluidDesignTokens();

// Create assets directory on activation
register_activation_hook(__FILE__, 'fluid_design_tokens_create_assets');

function fluid_design_tokens_create_assets() {
    $assets_dir = FDT_PLUGIN_PATH . 'assets/';
    if (!file_exists($assets_dir)) {
        wp_mkdir_p($assets_dir);
    }
}
