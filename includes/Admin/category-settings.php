<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function tenup_fpc_settings() { ?>
	<div class="wrap">
		<h2><?php _e('Primary Category Settings', 'wp-primary-category'); ?></h2>

        <?php $tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings'; ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo admin_url('admin.php?page=tenup_fpc_settings&amp;tab=settings'); ?>" class="nav-tab <?php echo $tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('General Settings', 'wp-primary-category'); ?></a>
        </h2>

        <?php if ((string) $tab === 'settings') {
            if (isset($_POST['info_update']) && current_user_can('manage_options')) {
                // Any of the WordPress data sanitization functions can be used here
                $tenup_fpc_primary_category = array_map('sanitize_text_field', $_POST['tenup_fpc_primary_category']);
                update_option('tenup_fpc_primary_category', $tenup_fpc_primary_category);

                echo '<div class="updated notice is-dismissible"><p>' . __('Settings updated!', 'wp-primary-category') . '</p></div>';
            }
            ?>
            <form method="post" action="">
                <h3><?php _e('Primary Category Settings', 'wp-primary-category'); ?></h3>

                <p><span class="dashicons dashicons-editor-help"></span> <?php _e('Select one or more post types from the list below to allow primary category selection.', 'wp-primary-category'); ?></p>
                <p>
                    <?php
                    $args = array(
                        'public'   => true,
                        '_builtin' => false
                     );
                       
                    $output = 'names'; // 'names' or 'objects' (default: 'names')
                    $operator = 'and'; // 'and' or 'or' (default: 'and')
                    
                    $allowPrimaryCategoryFor = get_option('tenup_fpc_primary_category');
                    // Output a list of only custom post types which are public
                    $postTypes = get_post_types( $args, $output, $operator );

                    foreach ($postTypes as $postType) {
                        $postTypeObject = get_post_type_object($postType);
                        $checked = (in_array($postType, $allowPrimaryCategoryFor) ? 'checked' : ''); ?>
                        <input type="checkbox" id="tenup_fpc_primary_category_<?php echo $postType; ?>" name="tenup_fpc_primary_category[]" value="<?php echo $postType; ?>" <?php echo $checked; ?>>
                        <label for="tenup_fpc_primary_category_<?php echo $postType; ?>"><?php echo $postTypeObject->labels->singular_name; ?> (<code><?php echo $postType; ?></code>)</label>
                        <br>
                    <?php } ?>
                </p>

                <p><input type="submit" name="info_update" class="button button-primary" value="<?php _e('Save Changes', 'wp-primary-category'); ?>"></p>
            </form>
            <?php
        } 
        ?>
	</div>
<?php
}
