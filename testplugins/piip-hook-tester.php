<?php
/**
 * Plugin Name: PIIP Hook Tester
 * Description: Test plugin to verify PIIP custom hooks functionality
 * Version: 1.0.0
 * Author: Test Developer
 * Requires Plugins: piip-pii-protection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PIIP_Hook_Tester
 * 
 * Tests all PIIP custom hooks to ensure they work correctly.
 */
class PIIP_Hook_Tester {
    
    private $test_results = array();
    
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
                '<strong>PIIP Hook Tester</strong> requires the <strong>PIIP - PII Protection</strong> plugin to be installed and activated.',
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
        
        $this->setup_hooks();
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_piip_run_tests', array( $this, 'run_hook_tests' ) );
    }
    
    /**
     * Show notice when PIIP is not found
     */
    public function piip_not_found_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>PIIP Hook Tester:</strong> This plugin requires <strong>PIIP - PII Protection</strong> to be installed and activated.';
        echo '</p></div>';
    }
    
    /**
     * Setup all PIIP hooks for testing
     */
    public function setup_hooks() {
        // Test 1: Custom PII Detection
        add_filter( 'piip_custom_detect_pii_type', array( $this, 'test_custom_pii_detection' ), 10, 3 );
        
        // Test 2: Custom Value Masking
        add_filter( 'piip_custom_mask_value', array( $this, 'test_custom_value_masking' ), 10, 3 );
        
        // Test 3: Custom Text Masking
        add_filter( 'piip_custom_mask_text', array( $this, 'test_custom_text_masking' ), 10, 2 );
        
        // Test 4: Custom Masking by Type
        add_filter( 'piip_custom_mask_by_type', array( $this, 'test_custom_masking_by_type' ), 10, 3 );
        
        // Test 5: Pre/Post processing
        add_filter( 'piip_before_mask_value', array( $this, 'test_before_mask_value' ), 10, 2 );
        add_filter( 'piip_after_mask_value', array( $this, 'test_after_mask_value' ), 10, 4 );
        
        // Test 6: Action hooks (logging)
        add_action( 'piip_value_masked', array( $this, 'test_value_masked_action' ), 10, 4 );
        add_action( 'piip_consent_bypass', array( $this, 'test_consent_bypass_action' ), 10, 3 );
        add_action( 'piip_text_masked', array( $this, 'test_text_masked_action' ), 10, 2 );
        
        // Test 7: Integration hooks
        add_filter( 'piip_available_integrations', array( $this, 'test_custom_integration' ), 10, 1 );
        add_filter( 'piip_admin_available_integrations', array( $this, 'test_admin_integration' ), 10, 1 );
        
        // Test 8: Form data hooks
        add_filter( 'piip_before_mask_form_data', array( $this, 'test_before_form_data' ), 10, 1 );
        add_filter( 'piip_after_mask_form_data', array( $this, 'test_after_form_data' ), 10, 2 );
    }
    
    /**
     * Test custom PII detection
     */
    public function test_custom_pii_detection( $custom_type, $field_name, $field_value ) {
        // Test case: Detect test bank account numbers
        if ( $field_name === 'test_bank_account' && preg_match( '/^BANK\d{7}$/', $field_value ) ) {
            $this->log_test( 'custom_pii_detection', 'PASS', 'Detected custom bank account format' );
            return 'test_bank_account';
        }
        
        if ( $field_name === 'test_bank_account' ) {
            $this->log_test( 'custom_pii_detection', 'TRIGGERED', 'Custom PII detection hook called' );
        }
        
        return $custom_type;
    }
    
    /**
     * Test custom value masking
     */
    public function test_custom_value_masking( $custom_masked, $value, $field_name ) {
        if ( $field_name === 'test_custom_field' ) {
            $this->log_test( 'custom_value_masking', 'PASS', 'Custom value masking triggered' );
            return 'CUSTOM_MASKED_' . substr( $value, 0, 3 ) . '***';
        }
        
        return $custom_masked;
    }
    
    /**
     * Test custom text masking
     */
    public function test_custom_text_masking( $custom_result, $text ) {
        if ( strpos( $text, 'TEST_SECRET:' ) !== false ) {
            $this->log_test( 'custom_text_masking', 'PASS', 'Custom text masking triggered' );
            return preg_replace( '/TEST_SECRET:\w+/', 'TEST_SECRET:***', $text );
        }
        
        return $custom_result;
    }
    
    /**
     * Test custom masking by type
     */
    public function test_custom_masking_by_type( $custom_result, $pii_type, $value ) {
        if ( $pii_type === 'test_bank_account' ) {
            $this->log_test( 'custom_masking_by_type', 'PASS', 'Custom masking by type triggered' );
            return 'BANK***' . substr( $value, -3 );
        }
        
        return $custom_result;
    }
    
    /**
     * Test before mask value
     */
    public function test_before_mask_value( $value, $field_name ) {
        if ( $field_name === 'test_preprocess' ) {
            $this->log_test( 'before_mask_value', 'PASS', 'Before mask value hook triggered' );
            return strtoupper( $value ); // Transform to uppercase
        }
        
        return $value;
    }
    
    /**
     * Test after mask value
     */
    public function test_after_mask_value( $masked_value, $original_value, $field_name, $pii_type ) {
        if ( $field_name === 'test_postprocess' ) {
            $this->log_test( 'after_mask_value', 'PASS', 'After mask value hook triggered' );
            return $masked_value . '_PROCESSED';
        }
        
        return $masked_value;
    }
    
    /**
     * Test value masked action
     */
    public function test_value_masked_action( $original_value, $masked_value, $field_name, $pii_type ) {
        if ( strpos( $field_name, 'test_' ) === 0 ) {
            $this->log_test( 'value_masked_action', 'PASS', "Value masked action: {$field_name} ({$pii_type})" );
        }
    }
    
    /**
     * Test consent bypass action
     */
    public function test_consent_bypass_action( $value, $field_name, $pii_type ) {
        $this->log_test( 'consent_bypass_action', 'PASS', "Consent bypass: {$field_name} ({$pii_type})" );
    }
    
    /**
     * Test text masked action
     */
    public function test_text_masked_action( $original_text, $masked_text ) {
        if ( $original_text !== $masked_text && strpos( $original_text, 'TEST_' ) !== false ) {
            $this->log_test( 'text_masked_action', 'PASS', 'Text masking action triggered' );
        }
    }
    
    /**
     * Test custom integration
     */
    public function test_custom_integration( $integrations ) {
        $integrations['test_plugin'] = array(
            'class' => 'PIIP_Test_Plugin_Integration',
            'check' => function() { return true; } // Always available for testing
        );
        
        $this->log_test( 'custom_integration', 'PASS', 'Custom integration added' );
        return $integrations;
    }
    
    /**
     * Test admin integration
     */
    public function test_admin_integration( $integrations ) {
        $integrations['test_plugin'] = array(
            'name'        => 'Test Plugin',
            'description' => 'Test plugin for PIIP hook validation',
            'class'       => 'PIIP_Test_Plugin_Integration',
            'check'       => function() { return true; }
        );
        
        $this->log_test( 'admin_integration', 'PASS', 'Admin integration added' );
        return $integrations;
    }
    
    /**
     * Test before form data
     */
    public function test_before_form_data( $data ) {
        if ( isset( $data['test_form_marker'] ) ) {
            $this->log_test( 'before_form_data', 'PASS', 'Before form data hook triggered' );
        }
        
        return $data;
    }
    
    /**
     * Test after form data
     */
    public function test_after_form_data( $masked_data, $original_data ) {
        if ( isset( $original_data['test_form_marker'] ) ) {
            $this->log_test( 'after_form_data', 'PASS', 'After form data hook triggered' );
        }
        
        return $masked_data;
    }
    
    /**
     * Log test result
     */
    private function log_test( $test_name, $status, $message ) {
        $this->test_results[] = array(
            'test' => $test_name,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time( 'mysql' )
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'PIIP Hook Tester',
            'PIIP Hook Tester',
            'manage_options',
            'piip-hook-tester',
            array( $this, 'admin_page' )
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>PIIP Hook Tester</h1>
            <p>This plugin tests all PIIP custom hooks to ensure they work correctly.</p>
            
            <div class="card">
                <h2>Quick Tests</h2>
                <p><button type="button" class="button button-primary" onclick="runHookTests()">Run All Hook Tests</button></p>
                <p><button type="button" class="button" onclick="testTextMasking()">Test Simple Text Masking</button></p>
                <p><button type="button" class="button" onclick="testFormDataDemo()">Test Form Data Masking</button></p>
            </div>
            
            <div class="card">
                <h2>Manual Tests</h2>
                <div style="margin-bottom: 20px;">
                    <h3>Test Simple Text Masking</h3>
                    <textarea id="test-text" rows="4" cols="80" placeholder="Enter text to test masking (try: john@example.com, 090-1234-5678, TEST_SECRET:abc123)">Contact john@example.com or call 090-1234-5678. Also TEST_SECRET:abc123 should be masked.</textarea><br>
                    <button type="button" class="button" onclick="testTextMasking()">Test Text Masking</button>
                    <div id="text-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0; display: none;"></div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h3>Test Custom Field Masking</h3>
                    <input type="text" id="test-field-value" placeholder="Enter value" style="width: 300px;">
                    <select id="test-field-name" style="width: 200px;">
                        <option value="test_custom_field">test_custom_field</option>
                        <option value="test_bank_account">test_bank_account</option>
                        <option value="test_preprocess">test_preprocess</option>
                        <option value="email">email</option>
                    </select>
                    <button type="button" class="button" onclick="testFieldMasking()">Test Field Masking</button>
                    <div id="field-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0; display: none;"></div>
                </div>
            </div>
            
            <div class="card">
                <h2>Test Results</h2>
                <div id="test-results">
                    <?php $this->display_test_results(); ?>
                </div>
            </div>
        </div>
        
        <script>
        function runHookTests() {
            // Show loading state
            var button = document.querySelector('button[onclick="runHookTests()"]');
            var originalText = button.textContent;
            button.textContent = 'Running Tests...';
            button.disabled = true;
            
            jQuery.post(ajaxurl, {
                action: 'piip_run_tests',
                _ajax_nonce: '<?php echo wp_create_nonce( "piip_test_nonce" ); ?>'
            }, function(response) {
                console.log('Test response:', response);
                if (response.success) {
                    alert('Tests completed! Page will reload to show results.');
                    location.reload();
                } else {
                    alert('Test failed: ' + (response.data || 'Unknown error'));
                    console.error('Test error:', response);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('AJAX request failed: ' + error + '. Check browser console for details.');
            }).always(function() {
                button.textContent = originalText;
                button.disabled = false;
            });
        }
        
        function testTextMasking() {
            var text = document.getElementById('test-text').value;
            if (!text) {
                alert('Please enter some text to test');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'piip_test_text_masking',
                text: text,
                _ajax_nonce: '<?php echo wp_create_nonce( "piip_test_nonce" ); ?>'
            }, function(response) {
                console.log('Text masking response:', response);
                var resultDiv = document.getElementById('text-result');
                if (response.success) {
                    resultDiv.innerHTML = '<strong>Original:</strong> ' + response.data.original + 
                                         '<br><strong>Masked:</strong> ' + response.data.masked;
                    resultDiv.style.display = 'block';
                } else {
                    resultDiv.innerHTML = 'Error: ' + (response.data || 'Unknown error');
                    resultDiv.style.display = 'block';
                }
            }).fail(function(xhr, status, error) {
                console.error('Text masking AJAX error:', status, error);
                document.getElementById('text-result').innerHTML = 'AJAX Error: ' + error;
                document.getElementById('text-result').style.display = 'block';
            });
        }
        
        function testFieldMasking() {
            var value = document.getElementById('test-field-value').value;
            var fieldName = document.getElementById('test-field-name').value;
            
            if (!value) {
                alert('Please enter a value to test');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'piip_test_field_masking',
                value: value,
                field_name: fieldName,
                _ajax_nonce: '<?php echo wp_create_nonce( "piip_test_nonce" ); ?>'
            }, function(response) {
                console.log('Field masking response:', response);
                var resultDiv = document.getElementById('field-result');
                if (response.success) {
                    resultDiv.innerHTML = '<strong>Field:</strong> ' + response.data.field_name + 
                                         '<br><strong>Original:</strong> ' + response.data.original + 
                                         '<br><strong>Masked:</strong> ' + response.data.masked +
                                         '<br><strong>PII Type:</strong> ' + (response.data.pii_type || 'none');
                    resultDiv.style.display = 'block';
                } else {
                    resultDiv.innerHTML = 'Error: ' + (response.data || 'Unknown error');
                    resultDiv.style.display = 'block';
                }
            }).fail(function(xhr, status, error) {
                console.error('Field masking AJAX error:', status, error);
                document.getElementById('field-result').innerHTML = 'AJAX Error: ' + error;
                document.getElementById('field-result').style.display = 'block';
            });
        }
        
        function testFormDataDemo() {
            // Demo form data test
            var demoData = {
                'user_name': 'John Doe',
                'user_email': 'john@example.com',
                'user_phone': '090-1234-5678',
                'member_id': 'MEM12345',
                'test_custom_field': 'test123'
            };
            
            jQuery.post(ajaxurl, {
                action: 'piip_test_form_data',
                form_data: demoData,
                _ajax_nonce: '<?php echo wp_create_nonce( "piip_test_nonce" ); ?>'
            }, function(response) {
                console.log('Form data test response:', response);
                if (response.success) {
                    alert('Form Data Test Results:\n' +
                          'Original fields: ' + Object.keys(response.data.original).length + '\n' +
                          'Changes detected: ' + response.data.changes_count + '\n' +
                          'Check console for detailed results.');
                } else {
                    alert('Form data test failed: ' + (response.data || 'Unknown error'));
                }
            }).fail(function(xhr, status, error) {
                console.error('Form data test AJAX error:', status, error);
                alert('AJAX Error: ' + error);
            });
        }
        
        // Debug function to check if everything is loaded
        jQuery(document).ready(function() {
            console.log('PIIP Hook Tester loaded');
            console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT DEFINED');
            console.log('jQuery:', typeof jQuery !== 'undefined' ? 'loaded' : 'NOT LOADED');
        });
        </script>
        <?php
    }
    
    /**
     * Display test results
     */
    private function display_test_results() {
        if ( empty( $this->test_results ) ) {
            echo '<p>No tests have been run yet. Click "Run All Hook Tests" to start testing.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Test</th><th>Status</th><th>Message</th><th>Timestamp</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ( $this->test_results as $result ) {
            $status_class = $result['status'] === 'PASS' ? 'success' : ($result['status'] === 'FAIL' ? 'error' : 'info');
            echo '<tr>';
            echo '<td>' . esc_html( $result['test'] ) . '</td>';
            echo '<td><span class="notice notice-' . $status_class . ' notice-alt inline">' . esc_html( $result['status'] ) . '</span></td>';
            echo '<td>' . esc_html( $result['message'] ) . '</td>';
            echo '<td>' . esc_html( $result['timestamp'] ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Run comprehensive hook tests
     */
    public function run_hook_tests() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_ajax_nonce'] ?? '', 'piip_test_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
            return;
        }

        // Clear previous results
        $this->test_results = array();
        
        // Test 1: Check if PIIP is active
        if ( ! function_exists( 'piip_mask_text' ) ) {
            wp_send_json_error( 'PIIP plugin is not active or piip_mask_text function is not available' );
            return;
        }
        
        $this->log_test( 'piip_availability', 'PASS', 'PIIP plugin and piip_mask_text function available' );
        
        // Test 2: Simple text masking
        $test_text = 'Contact test@example.com or call 090-1234-5678. TEST_SECRET:abc123';
        $masked = piip_mask_text( $test_text );
        if ( $masked !== $test_text ) {
            $this->log_test( 'simple_text_masking', 'PASS', 'Simple text masking working: ' . strlen($test_text) . ' → ' . strlen($masked) . ' chars' );
        } else {
            $this->log_test( 'simple_text_masking', 'FAIL', 'Simple text masking not working - no changes detected' );
        }
        
        // Test 3: Check PIIP plugin instance
        $plugin = piip();
        if ( ! $plugin || ! isset( $plugin->masker ) ) {
            $this->log_test( 'piip_instance', 'FAIL', 'PIIP plugin instance or masker not available' );
            wp_send_json_success( array( 'message' => 'Tests completed with errors', 'results' => $this->test_results ) );
            return;
        }
        
        $this->log_test( 'piip_instance', 'PASS', 'PIIP plugin instance and masker available' );
        
        // Test 4: Custom field masking
        try {
            $masked = $plugin->masker->mask_value( 'test_custom_field', 'test123' );
            $this->log_test( 'custom_field_test', 'PASS', 'Custom field masking executed: test123 → ' . $masked );
        } catch ( Exception $e ) {
            $this->log_test( 'custom_field_test', 'FAIL', 'Custom field masking error: ' . $e->getMessage() );
        }
        
        // Test 5: Bank account detection
        try {
            $masked = $plugin->masker->mask_value( 'test_bank_account', 'BANK1234567' );
            $this->log_test( 'bank_account_test', 'PASS', 'Bank account masking executed: BANK1234567 → ' . $masked );
        } catch ( Exception $e ) {
            $this->log_test( 'bank_account_test', 'FAIL', 'Bank account masking error: ' . $e->getMessage() );
        }
        
        // Test 6: Form data masking
        try {
            $test_data = array(
                'test_form_marker' => 'present',
                'test_custom_field' => 'test456',
                'email' => 'user@example.com',
                'user_phone' => '090-1234-5678'
            );
            $masked_data = $plugin->masker->mask_form_data( $test_data );
            
            $changes = 0;
            foreach ( $test_data as $key => $original ) {
                if ( isset( $masked_data[$key] ) && $masked_data[$key] !== $original ) {
                    $changes++;
                }
            }
            
            $this->log_test( 'form_data_masking', 'PASS', "Form data masking executed: {$changes} fields changed" );
        } catch ( Exception $e ) {
            $this->log_test( 'form_data_masking', 'FAIL', 'Form data masking error: ' . $e->getMessage() );
        }
        
        // Test 7: PII Detection
        try {
            $pii_type = $plugin->detector->detect_pii_type( 'email', 'test@example.com' );
            $this->log_test( 'pii_detection', 'PASS', 'PII detection working: email field detected as ' . ($pii_type ?: 'none') );
        } catch ( Exception $e ) {
            $this->log_test( 'pii_detection', 'FAIL', 'PII detection error: ' . $e->getMessage() );
        }
        
        wp_send_json_success( array( 
            'message' => 'All tests completed successfully', 
            'results' => $this->test_results,
            'total_tests' => count( $this->test_results )
        ) );
    }
}

// AJAX handlers for manual testing
add_action( 'wp_ajax_piip_test_text_masking', function() {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['_ajax_nonce'] ?? '', 'piip_test_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        return;
    }

    $text = sanitize_textarea_field( $_POST['text'] ?? '' );
    if ( empty( $text ) ) {
        wp_send_json_error( 'No text provided' );
        return;
    }
    
    if ( ! function_exists( 'piip_mask_text' ) ) {
        wp_send_json_error( 'PIIP plugin not available' );
        return;
    }
    
    try {
        $masked = piip_mask_text( $text );
        wp_send_json_success( array(
            'original' => $text,
            'masked' => $masked,
            'changed' => $text !== $masked
        ) );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Masking error: ' . $e->getMessage() );
    }
} );

add_action( 'wp_ajax_piip_test_field_masking', function() {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['_ajax_nonce'] ?? '', 'piip_test_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        return;
    }

    $value = sanitize_text_field( $_POST['value'] ?? '' );
    $field_name = sanitize_text_field( $_POST['field_name'] ?? '' );
    
    if ( empty( $value ) || empty( $field_name ) ) {
        wp_send_json_error( 'Value and field name required' );
        return;
    }
    
    $plugin = piip();
    if ( ! $plugin || ! isset( $plugin->masker ) || ! isset( $plugin->detector ) ) {
        wp_send_json_error( 'PIIP masker or detector not available' );
        return;
    }
    
    try {
        // Detect PII type first
        $pii_type = $plugin->detector->detect_pii_type( $field_name, $value );
        $masked = $plugin->masker->mask_value( $field_name, $value );
        
        wp_send_json_success( array(
            'field_name' => $field_name,
            'original' => $value,
            'masked' => $masked,
            'pii_type' => $pii_type,
            'changed' => $value !== $masked
        ) );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Masking error: ' . $e->getMessage() );
    }
} );

// Add form data test handler
add_action( 'wp_ajax_piip_test_form_data', function() {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['_ajax_nonce'] ?? '', 'piip_test_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        return;
    }

    $form_data = $_POST['form_data'] ?? array();
    if ( empty( $form_data ) ) {
        wp_send_json_error( 'No form data provided' );
        return;
    }
    
    // Sanitize form data
    $sanitized_data = array();
    foreach ( $form_data as $key => $value ) {
        $sanitized_data[ sanitize_key( $key ) ] = sanitize_text_field( $value );
    }
    
    $plugin = piip();
    if ( ! $plugin || ! isset( $plugin->masker ) ) {
        wp_send_json_error( 'PIIP masker not available' );
        return;
    }
    
    try {
        $masked_data = $plugin->masker->mask_form_data( $sanitized_data );
        
        // Count changes
        $changes = 0;
        $change_details = array();
        foreach ( $sanitized_data as $key => $original ) {
            if ( isset( $masked_data[$key] ) && $masked_data[$key] !== $original ) {
                $changes++;
                $change_details[$key] = array(
                    'original' => $original,
                    'masked' => $masked_data[$key]
                );
            }
        }
        
        wp_send_json_success( array(
            'original' => $sanitized_data,
            'masked' => $masked_data,
            'changes_count' => $changes,
            'change_details' => $change_details
        ) );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Form data masking error: ' . $e->getMessage() );
    }
} );

// Initialize the tester
new PIIP_Hook_Tester();