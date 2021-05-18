<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add primary category meta box
 *
 * @param post $post The post object
 */
function tenup_fpc_add_meta_boxes($post) {
    $allowPrimaryCategoryFor = get_option('tenup_fpc_primary_category');;

    add_meta_box('product_meta_box', __('Primary Category', 'wp-primary-category'), 'tenup_fpc_build_meta_box', $allowPrimaryCategoryFor, 'side', 'high');
}
add_action('add_meta_boxes', 'tenup_fpc_add_meta_boxes');

/**
 * Build custom field meta box along with WP nonce
 *
 * @param post $post The post object
 */
function tenup_fpc_build_meta_box($post) {
	wp_nonce_field(basename(__FILE__), 'tenup_fpc_meta_box_nonce');

    $wpPrimaryCategory = get_post_meta($post->ID, '_tenup_fpc_primary_category', true);
    if (empty($wpPrimaryCategory)) {
        $wpPrimaryCategory = 0;
    }
    
	?>
    <div class='inside'>
        <p>
            <label for="tenup_fpc_primary_category"><b><?php _e('', 'wp-primary-category'); ?></b></label>
            <select name="tenup_fpc_primary_category" id="tenup_fpc_primary_category" class="regular-text" data-primary-category="<?php echo $wpPrimaryCategory; ?>" style="width: 100%;">
                <option value="0"><?php _e('Select primary category...', 'wp-primary-category'); ?></option>
            </select>
            <br><small><?php _e('Select one or more categories then select a primary category from the dropdown above.', 'wp-primary-category'); ?></small>
        </p>
	</div>
	<?php
}

/**
 * Store custom field meta box data
 *
 * @param int $post_id The post ID
 */
function tenup_fpc_save_meta_box_data($post_id) {
    // Verify meta box nonce
    if (!isset($_POST['tenup_fpc_meta_box_nonce']) || !wp_verify_nonce($_POST['tenup_fpc_meta_box_nonce'], basename(__FILE__))) {
        return;
    }

    // Return if doing autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions (assume basic editing permissions)
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Store custom fields value
    if (isset($_REQUEST['tenup_fpc_primary_category'])) {
        update_post_meta($post_id, '_tenup_fpc_primary_category', sanitize_text_field($_POST['tenup_fpc_primary_category']));
    }
}
add_action('save_post', 'tenup_fpc_save_meta_box_data');
