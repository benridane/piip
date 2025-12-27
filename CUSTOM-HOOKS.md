# PIIP Custom Hook Usage Examples

This document provides examples of how to use PIIP's custom hooks for extending PII detection and masking functionality.

## Available Hooks

### Filters

1. **`piip_before_mask_value`** - Pre-process values before PII detection
2. **`piip_custom_mask_value`** - Complete custom override of value masking
3. **`piip_detected_pii_type`** - Modify detected PII type
4. **`piip_after_mask_value`** - Post-process masked values
5. **`piip_custom_mask_by_type`** - Custom masking for specific PII types
6. **`piip_before_mask_form_data`** - Pre-process form data
7. **`piip_custom_mask_form_data`** - Complete custom override of form data masking
8. **`piip_after_mask_form_data`** - Post-process masked form data
9. **`piip_before_detect_pii`** - Pre-process values before PII detection
10. **`piip_custom_detect_pii_type`** - Complete custom override of PII detection
11. **`piip_detected_pii_by_field_name`** - Filter PII type detected by field name
12. **`piip_detected_pii_by_value`** - Filter PII type detected by field value
13. **`piip_available_integrations`** - Register custom integrations
14. **`piip_admin_available_integrations`** - Register integrations for admin settings
15. **`piip_custom_integration_instance`** - Provide custom integration instances
16. **`piip_custom_mask_text`** - ✨ **Complete custom override of simple text masking**
17. **`piip_before_mask_text`** - ✨ **Pre-process text before masking**  
18. **`piip_after_mask_text`** - ✨ **Post-process text after masking**

### Actions

1. **`piip_value_masked`** - Fired after successful masking
2. **`piip_consent_bypass`** - Fired when user consent bypasses masking
3. **`piip_form_data_masked`** - Fired after form data masking
4. **`piip_text_masked`** - ✨ **Fired after simple text masking**

## Global Functions

### `piip_mask_text( $text )`
✨ **Simple function for masking any text content:**

```php
$text = "Call me at 090-1234-5678 or email john@example.com";
$masked = piip_mask_text( $text );
// Result: "Call me at ***-***-5678 or email j***@example.com"
```

## Usage Examples

### 1. Adding Support for New Community Plugin

```php
// Example: Add support for Ultimate Member plugin
add_filter( 'piip_available_integrations', 'add_ultimate_member_integration' );
function add_ultimate_member_integration( $integrations ) {
    $integrations['ultimate_member'] = array(
        'class' => 'PIIP_Ultimate_Member_Integration',
        'check' => function() {
            return class_exists( 'UM' );
        }
    );
    return $integrations;
}

add_filter( 'piip_admin_available_integrations', 'add_ultimate_member_admin_integration' );
function add_ultimate_member_admin_integration( $integrations ) {
    $integrations['ultimate_member'] = array(
        'name'        => 'Ultimate Member',
        'description' => __( 'User profiles and activities', 'your-textdomain' ),
        'class'       => 'PIIP_Ultimate_Member_Integration',
        'check'       => function() {
            return class_exists( 'UM' );
        }
    );
    return $integrations;
}

// Create the integration class
class PIIP_Ultimate_Member_Integration extends PIIP_Base_Integration {
    
    protected $slug = 'ultimate_member';
    
    public static function is_plugin_active() {
        return class_exists( 'UM' );
    }
    
    protected function init_hooks() {
        // Hook into Ultimate Member form submissions
        add_filter( 'um_before_save_filter_submitted_data', array( $this, 'mask_form_data' ), 10, 3 );
    }
    
    public function mask_form_data( $submitted, $key, $data ) {
        if ( is_array( $submitted ) ) {
            return $this->masker->mask_form_data( $submitted );
        }
        return $submitted;
    }
}
```

### 2. Custom PII Type Detection

```php
// Add custom PII type detection for Japanese bank accounts
add_filter( 'piip_custom_detect_pii_type', 'detect_japanese_bank_account', 10, 3 );
function detect_japanese_bank_account( $custom_type, $field_name, $field_value ) {
    // Check if field name suggests bank account
    if ( stripos( $field_name, 'bank' ) !== false || stripos( $field_name, '口座' ) !== false ) {
        // Japanese bank account: 7 digits
        if ( preg_match( '/^\d{7}$/', $field_value ) ) {
            return 'jp_bank_account';
        }
    }
    return $custom_type; // Return null to continue with default detection
}
```

### 3. Custom Masking for Specific Types

```php
// Add custom masking for Japanese bank accounts
add_filter( 'piip_custom_mask_by_type', 'mask_japanese_bank_account', 10, 3 );
function mask_japanese_bank_account( $custom_result, $pii_type, $value ) {
    if ( 'jp_bank_account' === $pii_type ) {
        // Mask middle digits: 1234567 -> 123***7
        return substr( $value, 0, 3 ) . '***' . substr( $value, -1 );
    }
    return $custom_result;
}
```

### 3. Override Specific Field Masking

```php
// Custom masking for specific field
add_filter( 'piip_custom_mask_value', 'custom_user_id_masking', 10, 3 );
function custom_user_id_masking( $custom_masked, $value, $field_name ) {
    if ( 'user_id' === $field_name ) {
        // Custom user ID masking
        return 'USER_' . str_repeat( '*', strlen( $value ) - 5 );
    }
    return $custom_masked;
}
```

### 4. Log Masking Events

```php
// Log all masking events for audit
add_action( 'piip_value_masked', 'log_masking_event', 10, 4 );
function log_masking_event( $original_value, $masked_value, $field_name, $pii_type ) {
    error_log( sprintf( 
        '[PIIP] Masked %s in field "%s" (type: %s)', 
        substr( $original_value, 0, 10 ) . '...', 
        $field_name, 
        $pii_type 
    ) );
}
```

### 5. Custom Address Detection

```php
// Enhanced address detection by value content
add_filter( 'piip_detected_pii_type', 'enhanced_address_detection', 10, 3 );
function enhanced_address_detection( $pii_type, $value, $field_name ) {
    // If no PII type detected, check for address patterns
    if ( ! $pii_type ) {
        // Japanese address patterns
        if ( preg_match( '/\d+[-\s]?\d+[-\s]?\d+/', $value ) && // Number pattern
             preg_match( '/[都道府県市区町村]/u', $value ) ) {    // Prefecture/city kanji
            return 'address';
        }
        
        // US address patterns
        if ( preg_match( '/\d+\s+\w+\s+(street|st|avenue|ave|road|rd|drive|dr|lane|ln|boulevard|blvd)/i', $value ) ) {
            return 'address';
        }
    }
    return $pii_type;
}
```

### 6. Bypass Masking for Admin Users

```php
// Skip masking for admin users
add_filter( 'piip_before_mask_value', 'admin_bypass_masking', 10, 2 );
function admin_bypass_masking( $value, $field_name ) {
    if ( current_user_can( 'manage_options' ) ) {
        // Return a special marker to bypass all masking
        add_filter( 'piip_custom_mask_value', function( $custom_masked, $val, $field ) use ( $value ) {
            return $val === $value ? $val : $custom_masked; // Return original value
        }, 999, 3 );
    }
    return $value;
}
```

### 7. Custom Form Data Processing

```php
// Process specific form types differently
add_filter( 'piip_before_mask_form_data', 'process_contact_form', 10, 1 );
function process_contact_form( $data ) {
    // Check if this is a contact form
    if ( isset( $data['form_type'] ) && 'contact' === $data['form_type'] ) {
        // Add custom processing
        $data['processed_by'] = 'custom_contact_processor';
    }
    return $data;
}
```

### 9. Simple Text Masking (New in v1.2.2)

```php
// Simple text masking without field context
$original_text = "Contact us at john@example.com or call 090-1234-5678";
$masked_text = piip_mask_text( $original_text );
// Result: "Contact us at j***@example.com or call ***-***-5678"

// Custom override for simple text masking
add_filter( 'piip_custom_mask_text', 'custom_simple_text_masking', 10, 2 );
function custom_simple_text_masking( $custom_result, $text ) {
    // Example: Replace all numbers with X
    if ( strpos( $text, 'secret:' ) !== false ) {
        return preg_replace( '/\d/', 'X', $text );
    }
    return $custom_result;
}

// Pre-process text before masking
add_filter( 'piip_before_mask_text', 'preprocess_text_for_masking' );
function preprocess_text_for_masking( $text ) {
    // Example: Normalize phone number format
    return preg_replace( '/(\d{3})-(\d{4})-(\d{4})/', '$1$2$3', $text );
}

// Post-process text after masking
add_filter( 'piip_after_mask_text', 'postprocess_masked_text', 10, 2 );
function postprocess_masked_text( $masked_text, $original_text ) {
    // Example: Add warning message if PII was detected
    if ( $masked_text !== $original_text ) {
        return $masked_text . ' [PII DETECTED AND MASKED]';
    }
    return $masked_text;
}

// Log all text masking events
add_action( 'piip_text_masked', 'log_text_masking', 10, 2 );
function log_text_masking( $original_text, $masked_text ) {
    if ( $original_text !== $masked_text ) {
        error_log( 'PIIP: Text masking applied - ' . strlen( $original_text ) . ' chars processed' );
    }
}
```

## Hook Priority Guidelines

- Use priority 10 (default) for most custom functionality
- Use priority 5 for early processing that should run before default logic
- Use priority 15-20 for post-processing that should run after default logic
- Use priority 999 for final overrides that should take precedence

## Performance Considerations

- Keep hook callbacks lightweight
- Cache expensive operations
- Use early returns to avoid unnecessary processing
- Consider the frequency of hook execution (per field vs per form)

## Security Notes

- Always validate and sanitize data in hook callbacks
- Be cautious with logging sensitive data
- Use appropriate capability checks for admin-only functionality
- Consider the implications of bypassing PII masking