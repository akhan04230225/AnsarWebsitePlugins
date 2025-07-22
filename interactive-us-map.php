<?php
/**
 * Plugin Name: Interactive US Map
 * Description: Render a contiguous US map with custom city pins via [interactive_us_map].
 * Version:     1.0
 * Author:      You
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Admin menu
function iusm_admin_menu() {
    add_menu_page(
        'Interactive US Map',
        'Interactive US Map',
        'manage_options',
        'iusm',
        'iusm_settings_page',
        'dashicons-location-alt'
    );
}
add_action( 'admin_menu', 'iusm_admin_menu' );

// Settings page
function iusm_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $cities  = get_option( 'iusm_cities', [] );
    $api_key = get_option( 'iusm_api_key', '' );

    // Add city
    if ( isset( $_POST['iusm_add_city'] ) ) {
        check_admin_referer( 'iusm_save_city' );
        $label   = sanitize_text_field( $_POST['iusm_label'] );
        $lat     = floatval( $_POST['iusm_lat'] );
        $lng     = floatval( $_POST['iusm_lng'] );
        $page_id = intval( $_POST['iusm_page_id'] );
        if ( $label && $lat && $lng && $page_id ) {
            $cities[] = [
                'label'   => $label,
                'lat'     => $lat,
                'lng'     => $lng,
                'page_id' => $page_id,
            ];
            update_option( 'iusm_cities', $cities );
            echo '<div class="updated"><p>City added.</p></div>';
        }
    }

    // Delete city
    if ( isset( $_GET['iusm_delete'] ) ) {
        $index = intval( $_GET['iusm_delete'] );
        if ( isset( $cities[$index] ) ) {
            unset( $cities[$index] );
            $cities = array_values( $cities );
            update_option( 'iusm_cities', $cities );
            echo '<div class="updated"><p>City removed.</p></div>';
        }
    }

    // Save API key
    if ( isset( $_POST['iusm_save_api'] ) ) {
        check_admin_referer( 'iusm_save_api' );
        $api_key = sanitize_text_field( $_POST['iusm_api_key'] );
        update_option( 'iusm_api_key', $api_key );
        echo '<div class="updated"><p>API key saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Interactive US Map</h1>

        <h2>API Key</h2>
        <form method="post">
            <?php wp_nonce_field( 'iusm_save_api' ); ?>
            <p>
                <label for="iusm_api_key">Google Maps API Key:</label>
                <input type="text" name="iusm_api_key" id="iusm_api_key" value="<?php echo esc_attr( $api_key ); ?>" style="width:400px;" />
                <button type="submit" name="iusm_save_api" class="button button-primary">Save Key</button>
            </p>
        </form>

        <h2>Cities</h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Page</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $cities ) : foreach ( $cities as $i => $c ) : ?>
                    <tr>
                        <td><?php echo esc_html( $c['label'] ); ?></td>
                        <td><?php echo esc_html( $c['lat'] ); ?></td>
                        <td><?php echo esc_html( $c['lng'] ); ?></td>
                        <td><?php echo esc_html( get_the_title( $c['page_id'] ) ); ?></td>
                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=iusm&iusm_delete=' . $i ) ); ?>" onclick="return confirm('Delete city?');">Delete</a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5">No cities added.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>Add New City</h2>
        <form method="post">
            <?php wp_nonce_field( 'iusm_save_city' ); ?>
            <table class="form-table" style="max-width:600px;">
                <tr>
                    <th scope="row"><label for="iusm_label">Label</label></th>
                    <td><input name="iusm_label" id="iusm_label" type="text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="iusm_lat">Latitude</label></th>
                    <td><input name="iusm_lat" id="iusm_lat" type="text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="iusm_lng">Longitude</label></th>
                    <td><input name="iusm_lng" id="iusm_lng" type="text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="iusm_page_id">Page Link</label></th>
                    <td>
                        <?php wp_dropdown_pages([
                            'name'             => 'iusm_page_id',
                            'show_option_none' => '&mdash; Select Page &mdash;',
                            'option_none_value'=> '0',
                            'selected'         => 0,
                        ]); ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="iusm_add_city" class="button button-primary">Add City</button>
            </p>
        </form>
    </div>
    <?php
}

// Shortcode
function iusm_render_map() {
    $cities  = get_option( 'iusm_cities', [] );
    $api_key = get_option( 'iusm_api_key', '' );
    if ( ! $api_key ) {
        return '<p>Please set a Google Maps API key.</p>';
    }

    wp_enqueue_style( 'interactive-us-map', plugin_dir_url( __FILE__ ) . 'interactive-us-map.css', [], '1.0' );
    $handle = 'google-maps';
    if ( ! wp_script_is( $handle, 'enqueued' ) ) {
        $src = 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&callback=initIUSMMap';
        wp_enqueue_script( $handle, $src, [], null, true );
    }
    wp_enqueue_script( 'iusm-map', plugin_dir_url( __FILE__ ) . 'interactive-us-map.js', [ $handle ], '1.0', true );

    $markers = [];
    foreach ( $cities as $c ) {
        $markers[] = [
            'label' => $c['label'],
            'lat'   => floatval( $c['lat'] ),
            'lng'   => floatval( $c['lng'] ),
            'url'   => get_permalink( $c['page_id'] ),
        ];
    }
    $markers_json = wp_json_encode( $markers );
    $init_js = "window.IUSM_MARKERS = $markers_json;";
    wp_add_inline_script( 'iusm-map', $init_js, 'before' );

    return '<div id="iusm-map" style="height:500px;"></div>';
}
add_shortcode( 'interactive_us_map', 'iusm_render_map' );
