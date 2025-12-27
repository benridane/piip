<?php
/**
 * Plugin Name: PIIP Integration Demo
 * Description: Demo plugin showing how to integrate with PIIP for PII protection
 * Version: 1.0.0
 * Author: Demo Developer
 * Requires Plugins: piip-pii-protection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Demo Community Plugin Integration
 * 
 * This demonstrates how a third-party plugin can integrate with PIIP
 * for automatic PII masking without modifying PIIP's core code.
 */
class PIIP_Integration_Demo {
    
    public function __construct() {
        // Check if PIIP is available before proceeding
        add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
        
        // Add deactivation hook if PIIP is not found
        register_activation_hook( __FILE__, array( $this, 'check_piip_dependency' ) );
    }
    
    /**
     * Check PIIP dependency on activation
     */
    public function check_piip_dependency() {
        if ( ! function_exists( 'piip' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( 
                '<strong>PIIP Integration Demo</strong> requires the <strong>PIIP - PII Protection</strong> plugin to be installed and activated.',
                'Plugin Dependency Error',
                array( 'back_link' => true )
            );
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Only proceed if PIIP is available
        if ( ! function_exists( 'piip' ) ) {
            add_action( 'admin_notices', array( $this, 'piip_not_found_notice' ) );
            return;
        }
        
        $this->setup_integration();
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Simulate a community plugin form
        add_action( 'wp_ajax_demo_submit_form', array( $this, 'handle_form_submission' ) );
        add_action( 'wp_ajax_nopriv_demo_submit_form', array( $this, 'handle_form_submission' ) );
    }
    
    /**
     * Show notice when PIIP is not found
     */
    public function piip_not_found_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>PIIP Integration Demo:</strong> This plugin requires <strong>PIIP - PII Protection</strong> to be installed and activated.';
        echo '</p></div>';
    }
    
    /**
     * Setup PIIP integration
     */
    public function setup_integration() {
        // Register our fake plugin integration
        add_filter( 'piip_available_integrations', array( $this, 'register_integration' ) );
        add_filter( 'piip_admin_available_integrations', array( $this, 'register_admin_integration' ) );
        
        // Add custom PII type for this demo
        add_filter( 'piip_custom_detect_pii_type', array( $this, 'detect_member_id' ), 10, 3 );
        add_filter( 'piip_custom_mask_by_type', array( $this, 'mask_member_id' ), 10, 3 );
    }
    
    /**
     * Register integration with PIIP
     */
    public function register_integration( $integrations ) {
        $integrations['demo_community'] = array(
            'class' => 'PIIP_Demo_Community_Integration',
            'check' => array( $this, 'is_demo_plugin_active' )
        );
        
        return $integrations;
    }
    
    /**
     * Register integration for admin settings
     */
    public function register_admin_integration( $integrations ) {
        $integrations['demo_community'] = array(
            'name'        => 'Demo Community Plugin',
            'description' => 'Demo integration for testing PIIP hooks',
            'class'       => 'PIIP_Demo_Community_Integration',
            'check'       => array( $this, 'is_demo_plugin_active' )
        );
        
        return $integrations;
    }
    
    /**
     * Check if demo plugin is active (always true for demo)
     */
    public function is_demo_plugin_active() {
        return true;
    }
    
    /**
     * Detect custom member ID PII type
     */
    public function detect_member_id( $custom_type, $field_name, $field_value ) {
        // Demo: Detect member IDs like MEM12345
        if ( stripos( $field_name, 'member' ) !== false && preg_match( '/^MEM\d{5}$/', $field_value ) ) {
            return 'demo_member_id';
        }
        
        return $custom_type;
    }
    
    /**
     * Mask member ID
     */
    public function mask_member_id( $custom_result, $pii_type, $value ) {
        if ( $pii_type === 'demo_member_id' ) {
            return 'MEM***' . substr( $value, -2 );
        }
        
        return $custom_result;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'PIIP Integration Demo',
            'PIIP Integration Demo',
            'manage_options',
            'piip-integration-demo',
            array( $this, 'admin_page' )
        );
    }
    
    /**
     * Admin page with demo form
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>PIIP Integration Demo</h1>
            <p>This page demonstrates how a community plugin can integrate with PIIP for automatic PII protection.</p>
            
            <div class="card">
                <h2>Demo Form Submission</h2>
                <p>Try submitting this form with PII data to see how PIIP automatically masks it:</p>
                
                <form id="demo-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Name</th>
                            <td><input type="text" name="user_name" placeholder="John Doe" style="width: 300px;"></td>
                        </tr>
                        <tr>
                            <th scope="row">Email</th>
                            <td><input type="email" name="user_email" placeholder="john@example.com" style="width: 300px;"></td>
                        </tr>
                        <tr>
                            <th scope="row">Phone</th>
                            <td><input type="text" name="user_phone" placeholder="090-1234-5678" style="width: 300px;"></td>
                        </tr>
                        <tr>
                            <th scope="row">Member ID</th>
                            <td><input type="text" name="member_id" placeholder="MEM12345" style="width: 300px;"></td>
                        </tr>
                        <tr>
                            <th scope="row">Address</th>
                            <td><textarea name="user_address" rows="3" cols="40" placeholder="123 Main St, Tokyo, Japan"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Comments</th>
                            <td><textarea name="comments" rows="4" cols="40" placeholder="Contact me at john@example.com or 090-1234-5678"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Consent Phrase</th>
                            <td>
                                <input type="checkbox" id="add_consent" onchange="toggleConsent()">
                                <label for="add_consent">Add consent phrase to bypass masking</label>
                                <br><small>If checked, "I consent to share my personal information" will be added to comments</small>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="button" class="button button-primary" onclick="submitDemoForm()">Submit Form (with PIIP masking)</button>
                        <button type="button" class="button" onclick="submitDemoFormRaw()">Submit Form (without masking)</button>
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>Submission Results</h2>
                <div id="submission-results" style="display: none;">
                    <h3>Original Data</h3>
                    <pre id="original-data" style="background: #f0f0f0; padding: 10px; overflow: auto;"></pre>
                    
                    <h3>Masked Data (what gets saved)</h3>
                    <pre id="masked-data" style="background: #e7f5e7; padding: 10px; overflow: auto;"></pre>
                    
                    <h3>Masking Summary</h3>
                    <div id="masking-summary"></div>
                </div>
            </div>
            
            <div class="card">
                <h2>Integration Code Example</h2>
                <p>Here's how this demo plugin integrates with PIIP:</p>
                <pre style="background: #f9f9f9; padding: 15px; overflow: auto; font-size: 12px;">
// 1. Register integration
add_filter( 'piip_available_integrations', 'register_integration' );
function register_integration( $integrations ) {
    $integrations['demo_community'] = array(
        'class' => 'PIIP_Demo_Community_Integration',
        'check' => 'is_plugin_active_callback'
    );
    return $integrations;
}

// 2. Handle form submission with PIIP masking
function handle_form_submission() {
    $form_data = $_POST; // Raw form data
    
    // Apply PIIP masking
    if ( function_exists( 'piip' ) ) {
        $plugin = piip();
        if ( isset( $plugin->masker ) ) {
            $masked_data = $plugin->masker->mask_form_data( $form_data );
        }
    }
    
    // Save masked data to database
    save_to_database( $masked_data );
}

// 3. Custom PII detection
add_filter( 'piip_custom_detect_pii_type', 'detect_custom_pii', 10, 3 );
function detect_custom_pii( $custom_type, $field_name, $field_value ) {
    if ( preg_match( '/^MEM\d{5}$/', $field_value ) ) {
        return 'member_id';
    }
    return $custom_type;
}
                </pre>
            </div>
        </div>
        
        <script>
        function toggleConsent() {
            var checkbox = document.getElementById('add_consent');
            var commentsField = document.querySelector('textarea[name="comments"]');
            
            if (checkbox.checked) {
                if (commentsField.value && !commentsField.value.includes('I consent to share')) {
                    commentsField.value += '\n\nI consent to share my personal information';
                } else if (!commentsField.value) {
                    commentsField.value = 'I consent to share my personal information';
                }
            } else {
                commentsField.value = commentsField.value.replace(/\n\nI consent to share my personal information/g, '');
                commentsField.value = commentsField.value.replace(/I consent to share my personal information/g, '');
            }
        }
        
        function submitDemoForm() {
            var formData = new FormData(document.getElementById('demo-form'));
            formData.append('action', 'demo_submit_form');
            formData.append('use_masking', '1');
            
            jQuery.post(ajaxurl, jQuery.param(jQuery.extend({}, 
                Object.fromEntries(formData.entries())
            )), function(response) {
                displayResults(response);
            });
        }
        
        function submitDemoFormRaw() {
            var formData = new FormData(document.getElementById('demo-form'));
            formData.append('action', 'demo_submit_form');
            formData.append('use_masking', '0');
            
            jQuery.post(ajaxurl, jQuery.param(jQuery.extend({}, 
                Object.fromEntries(formData.entries())
            )), function(response) {
                displayResults(response);
            });
        }
        
        function displayResults(response) {
            var resultsDiv = document.getElementById('submission-results');
            var originalDiv = document.getElementById('original-data');
            var maskedDiv = document.getElementById('masked-data');
            var summaryDiv = document.getElementById('masking-summary');
            
            if (response.success) {
                originalDiv.textContent = JSON.stringify(response.data.original, null, 2);
                maskedDiv.textContent = JSON.stringify(response.data.processed, null, 2);
                
                var summary = '';
                if (response.data.masking_applied) {
                    summary = '<span style="color: green;">✓ PIIP masking was applied</span><br>';
                    summary += '<strong>Changes detected:</strong><ul>';
                    for (var field in response.data.original) {
                        if (response.data.original[field] !== response.data.processed[field]) {
                            summary += '<li><code>' + field + '</code>: ' + 
                                      response.data.original[field] + ' → ' + 
                                      response.data.processed[field] + '</li>';
                        }
                    }
                    summary += '</ul>';
                } else {
                    summary = '<span style="color: orange;">⚠ No PIIP masking applied</span>';
                }
                summaryDiv.innerHTML = summary;
                
                resultsDiv.style.display = 'block';
            } else {
                alert('Error: ' + response.data);
            }
        }
        </script>
        <?php
    }
    
    /**
     * Handle demo form submission
     */
    public function handle_form_submission() {
        // Get form data
        $original_data = array();
        $fields = array( 'user_name', 'user_email', 'user_phone', 'member_id', 'user_address', 'comments' );
        
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $original_data[ $field ] = sanitize_textarea_field( $_POST[ $field ] );
            }
        }
        
        $use_masking = ! empty( $_POST['use_masking'] );
        $processed_data = $original_data;
        
        if ( $use_masking && function_exists( 'piip' ) ) {
            $plugin = piip();
            if ( isset( $plugin->masker ) ) {
                $processed_data = $plugin->masker->mask_form_data( $original_data );
            }
        }
        
        wp_send_json_success( array(
            'original' => $original_data,
            'processed' => $processed_data,
            'masking_applied' => $use_masking,
            'timestamp' => current_time( 'mysql' )
        ) );
    }
}

/**
 * Demo Integration Class
 * 
 * This would be the actual integration class for a real community plugin.
 */
class PIIP_Demo_Community_Integration {
    
    protected $slug = 'demo_community';
    protected $masker;
    protected $detector;
    
    /**
     * Constructor
     */
    public function __construct( $masker = null, $detector = null ) {
        $this->masker = $masker;
        $this->detector = $detector;
        $this->init_hooks();
    }
    
    /**
     * Check if plugin is active
     */
    public static function is_plugin_active() {
        return true; // Always active for demo
    }
    
    /**
     * Initialize hooks
     */
    protected function init_hooks() {
        // In a real plugin, this would hook into the plugin's form submission hooks
        // For demo purposes, we'll just log that the integration is active
        add_action( 'admin_notices', array( $this, 'show_integration_notice' ) );
    }
    
    /**
     * Show integration notice
     */
    public function show_integration_notice() {
        if ( get_current_screen() && get_current_screen()->id === 'tools_page_piip-integration-demo' ) {
            echo '<div class="notice notice-success"><p>';
            echo '<strong>PIIP Integration Active:</strong> Demo Community Plugin integration is working!';
            echo '</p></div>';
        }
    }
}

// Initialize the demo
new PIIP_Integration_Demo();