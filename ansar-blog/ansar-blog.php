<?php
/**
 * Plugin Name: Ansar Community Blog
 * Description: Provides a community blog with featured articles, categories, search, subscriptions, and a rich editor for authors.
 * Version:     1.0.0
 * Author:      Majlis Ansarullah USA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ansar_Community_Blog {
    const POST_TYPE             = 'ansar_blog_article';
    const TAXONOMY              = 'ansar_blog_category';
    const FEATURED_META_KEY     = '_ansar_blog_featured';
    const ATTACHED_IMAGE_META   = '_ansar_blog_attached_image';
    const SUBSCRIBERS_OPTION    = 'ansar_blog_subscribers';
    const BLOG_PAGE_SLUG        = 'community-blog';
    const CATEGORIES_PAGE_SLUG  = 'ansar-blog-categories';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type_and_taxonomy' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );
        add_action( 'admin_post_ansar_blog_save_article', [ __CLASS__, 'handle_save_article' ] );
        add_action( 'admin_post_ansar_blog_feature_article', [ __CLASS__, 'handle_feature_article' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
        add_shortcode( 'ansar_blog', [ __CLASS__, 'render_blog_shortcode' ] );
        add_shortcode( 'ansar_blog_all_categories', [ __CLASS__, 'render_all_categories_shortcode' ] );

        add_action( 'wp_ajax_ansar_blog_load_more', [ __CLASS__, 'ajax_load_more_articles' ] );
        add_action( 'wp_ajax_nopriv_ansar_blog_load_more', [ __CLASS__, 'ajax_load_more_articles' ] );
        add_action( 'wp_ajax_ansar_blog_search', [ __CLASS__, 'ajax_search_articles' ] );
        add_action( 'wp_ajax_nopriv_ansar_blog_search', [ __CLASS__, 'ajax_search_articles' ] );
        add_action( 'wp_ajax_ansar_blog_subscribe', [ __CLASS__, 'ajax_subscribe' ] );
        add_action( 'wp_ajax_nopriv_ansar_blog_subscribe', [ __CLASS__, 'ajax_subscribe' ] );

        register_activation_hook( __FILE__, [ __CLASS__, 'on_activate' ] );
    }

    public static function register_post_type_and_taxonomy() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Community Blog Articles', 'ansar-community-blog' ),
                'singular_name'      => __( 'Community Blog Article', 'ansar-community-blog' ),
                'add_new'            => __( 'Add Article', 'ansar-community-blog' ),
                'add_new_item'       => __( 'Add New Article', 'ansar-community-blog' ),
                'edit_item'          => __( 'Edit Article', 'ansar-community-blog' ),
                'new_item'           => __( 'New Article', 'ansar-community-blog' ),
                'view_item'          => __( 'View Article', 'ansar-community-blog' ),
                'view_items'         => __( 'View Articles', 'ansar-community-blog' ),
                'search_items'       => __( 'Search Articles', 'ansar-community-blog' ),
                'not_found'          => __( 'No articles found', 'ansar-community-blog' ),
                'not_found_in_trash' => __( 'No articles found in trash', 'ansar-community-blog' ),
            ],
            'public'              => true,
            'has_archive'         => false,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-welcome-write-blog',
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'show_in_rest'        => true,
            'rewrite'             => [ 'slug' => 'community-article' ],
        ] );

        register_taxonomy( self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Blog Categories', 'ansar-community-blog' ),
                'singular_name' => __( 'Blog Category', 'ansar-community-blog' ),
                'add_new_item'  => __( 'Add New Blog Category', 'ansar-community-blog' ),
                'search_items'  => __( 'Search Categories', 'ansar-community-blog' ),
                'all_items'     => __( 'All Categories', 'ansar-community-blog' ),
            ],
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'community-blog-category' ],
        ] );
    }

    public static function register_admin_menu() {
        add_menu_page(
            __( 'Ansar Blog', 'ansar-community-blog' ),
            __( 'Ansar Blog', 'ansar-community-blog' ),
            'edit_posts',
            'ansar-community-blog',
            [ __CLASS__, 'render_admin_page' ],
            'dashicons-edit',
            26
        );
    }

    public static function enqueue_admin_assets( $hook_suffix ) {
        if ( 'toplevel_page_ansar-community-blog' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'ansar-community-blog-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), [], '1.0.0' );
        wp_enqueue_script( 'ansar-community-blog-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), [ 'jquery' ], '1.0.0', true );
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'ansar-community-blog' ) );
        }

        $message_code = isset( $_GET['ansar_blog_message'] ) ? sanitize_text_field( wp_unslash( $_GET['ansar_blog_message'] ) ) : '';
        $categories   = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ] );
        $recent_posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        ?>
        <div class="wrap ansar-blog-admin">
            <h1><?php esc_html_e( 'Ansar Community Blog', 'ansar-community-blog' ); ?></h1>
            <?php if ( 'success' === $message_code ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Article saved successfully.', 'ansar-community-blog' ); ?></p></div>
            <?php elseif ( 'error' === $message_code ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'There was a problem saving the article. Please review the form and try again.', 'ansar-community-blog' ); ?></p></div>
            <?php elseif ( 'featured' === $message_code ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Featured article updated.', 'ansar-community-blog' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ansar-blog-form">
                <?php wp_nonce_field( 'ansar_blog_save_article', 'ansar_blog_nonce' ); ?>
                <input type="hidden" name="action" value="ansar_blog_save_article">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ansar_blog_title"><?php esc_html_e( 'Article Title', 'ansar-community-blog' ); ?></label></th>
                            <td><input type="text" id="ansar_blog_title" name="ansar_blog_title" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Article Content', 'ansar-community-blog' ); ?></th>
                            <td>
                                <?php
                                wp_editor( '', 'ansar_blog_content', [
                                    'textarea_name' => 'ansar_blog_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 15,
                                ] );
                                ?>
                                <p class="description"><?php esc_html_e( 'Use the editor to format your article and insert images within the content.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ansar_blog_new_category"><?php esc_html_e( 'Create New Category', 'ansar-community-blog' ); ?></label></th>
                            <td>
                                <input type="text" id="ansar_blog_new_category" name="ansar_blog_new_category" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Community Service', 'ansar-community-blog' ); ?>">
                                <p class="description"><?php esc_html_e( 'Leave blank if you do not wish to create a new category.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ansar_blog_existing_category"><?php esc_html_e( 'Assign to Category', 'ansar-community-blog' ); ?></label></th>
                            <td>
                                <select id="ansar_blog_existing_category" name="ansar_blog_existing_category">
                                    <option value=""><?php esc_html_e( '— Select a category —', 'ansar-community-blog' ); ?></option>
                                    <?php foreach ( $categories as $category ) : ?>
                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Existing categories appear in this list. Newly created categories will be saved and shown here after saving.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ansar_blog_attached_image"><?php esc_html_e( 'Attach Reference Image', 'ansar-community-blog' ); ?></label></th>
                            <td>
                                <input type="file" id="ansar_blog_attached_image" name="ansar_blog_attached_image" accept="image/*">
                                <p class="description"><?php esc_html_e( 'Optional image used as the featured article artwork when the article is marked as featured.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ansar_blog_excerpt"><?php esc_html_e( 'Short Summary', 'ansar-community-blog' ); ?></label></th>
                            <td>
                                <textarea id="ansar_blog_excerpt" name="ansar_blog_excerpt" rows="3" class="large-text" maxlength="300"></textarea>
                                <p class="description"><?php esc_html_e( 'Optional excerpt that appears on the blog cards. If left blank, an excerpt will be generated automatically.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Featured Article', 'ansar-community-blog' ); ?></th>
                            <td>
                                <label for="ansar_blog_featured">
                                    <input type="checkbox" id="ansar_blog_featured" name="ansar_blog_featured" value="1">
                                    <?php esc_html_e( 'Mark this article as the featured article.', 'ansar-community-blog' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Only one article can be featured at a time. Previous featured articles will be unmarked automatically.', 'ansar-community-blog' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Publish Article', 'ansar-community-blog' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Recent Articles', 'ansar-community-blog' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'ansar-community-blog' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'ansar-community-blog' ); ?></th>
                        <th><?php esc_html_e( 'Featured?', 'ansar-community-blog' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'ansar-community-blog' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ansar-community-blog' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $recent_posts ) : ?>
                    <?php foreach ( $recent_posts as $post ) :
                        $terms        = get_the_terms( $post, self::TAXONOMY );
                        $term_names   = $terms ? wp_list_pluck( $terms, 'name' ) : [];
                        $is_featured  = (bool) get_post_meta( $post->ID, self::FEATURED_META_KEY, true );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
                            <td><?php echo esc_html( implode( ', ', $term_names ) ); ?></td>
                            <td><?php echo $is_featured ? '&#10003;' : '&mdash;'; ?></td>
                            <td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
                            <td>
                                <?php if ( $is_featured ) : ?>
                                    <button type="button" class="button button-secondary" disabled><?php esc_html_e( 'Currently Featured', 'ansar-community-blog' ); ?></button>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'ansar_blog_feature_article', 'ansar_blog_feature_nonce' ); ?>
                                        <input type="hidden" name="action" value="ansar_blog_feature_article">
                                        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
                                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Set as Featured', 'ansar-community-blog' ); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No articles available yet.', 'ansar-community-blog' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    public static function handle_save_article() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'ansar-community-blog' ) );
        }

        if ( ! isset( $_POST['ansar_blog_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ansar_blog_nonce'] ), 'ansar_blog_save_article' ) ) {
            wp_die( __( 'Security check failed.', 'ansar-community-blog' ) );
        }

        $redirect = add_query_arg( 'ansar_blog_message', 'error', wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ansar-community-blog' ) );

        $title = isset( $_POST['ansar_blog_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ansar_blog_title'] ) ) : '';
        $content = isset( $_POST['ansar_blog_content'] ) ? wp_kses_post( wp_unslash( $_POST['ansar_blog_content'] ) ) : '';
        $excerpt = isset( $_POST['ansar_blog_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ansar_blog_excerpt'] ) ) : '';
        $new_category_name = isset( $_POST['ansar_blog_new_category'] ) ? sanitize_text_field( wp_unslash( $_POST['ansar_blog_new_category'] ) ) : '';
        $existing_category = isset( $_POST['ansar_blog_existing_category'] ) ? intval( $_POST['ansar_blog_existing_category'] ) : 0;
        $featured = isset( $_POST['ansar_blog_featured'] ) ? 1 : 0;

        if ( empty( $title ) || empty( $content ) ) {
            wp_safe_redirect( $redirect );
            exit;
        }

        $terms = [];
        if ( $existing_category ) {
            $terms[] = $existing_category;
        }

        if ( $new_category_name ) {
            $existing = get_term_by( 'name', $new_category_name, self::TAXONOMY );
            if ( $existing ) {
                $terms[] = (int) $existing->term_id;
            } else {
                $created = wp_insert_term( $new_category_name, self::TAXONOMY );
                if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
                    $terms[] = (int) $created['term_id'];
                }
            }
        }

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( $terms ) {
            wp_set_post_terms( $post_id, $terms, self::TAXONOMY, false );
        }

        if ( $excerpt ) {
            wp_update_post( [
                'ID'           => $post_id,
                'post_excerpt' => $excerpt,
            ] );
        } else {
            $generated_excerpt = wp_trim_words( wp_strip_all_tags( $content ), 40, '…' );
            wp_update_post( [
                'ID'           => $post_id,
                'post_excerpt' => $generated_excerpt,
            ] );
        }

        $attached_image_id = 0;
        if ( ! empty( $_FILES['ansar_blog_attached_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload( 'ansar_blog_attached_image', $post_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                $attached_image_id = (int) $attachment_id;
                update_post_meta( $post_id, self::ATTACHED_IMAGE_META, $attached_image_id );
                set_post_thumbnail( $post_id, $attached_image_id );
            }
        }

        if ( $featured ) {
            update_post_meta( $post_id, self::FEATURED_META_KEY, 1 );
            self::unset_featured_from_other_articles( $post_id );
        } else {
            update_post_meta( $post_id, self::FEATURED_META_KEY, 0 );
        }

        self::notify_subscribers( $post_id );

        $redirect = add_query_arg( 'ansar_blog_message', 'success', admin_url( 'admin.php?page=ansar-community-blog' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function handle_feature_article() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'ansar-community-blog' ) );
        }

        check_admin_referer( 'ansar_blog_feature_article', 'ansar_blog_feature_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;

        if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
            $redirect = add_query_arg( 'ansar_blog_message', 'error', admin_url( 'admin.php?page=ansar-community-blog' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        update_post_meta( $post_id, self::FEATURED_META_KEY, 1 );
        self::unset_featured_from_other_articles( $post_id );

        $redirect = add_query_arg( 'ansar_blog_message', 'featured', admin_url( 'admin.php?page=ansar-community-blog' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    private static function unset_featured_from_other_articles( $current_post_id ) {
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post__not_in'   => [ $current_post_id ],
            'meta_query'     => [
                [
                    'key'   => self::FEATURED_META_KEY,
                    'value' => 1,
                ],
            ],
            'fields'         => 'ids',
        ];

        $query = new WP_Query( $args );
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                update_post_meta( $post_id, self::FEATURED_META_KEY, 0 );
            }
        }
    }

    private static function notify_subscribers( $post_id ) {
        $subscribers = get_option( self::SUBSCRIBERS_OPTION, [] );
        if ( empty( $subscribers ) || ! is_array( $subscribers ) ) {
            return;
        }

        $post    = get_post( $post_id );
        $title   = get_the_title( $post );
        $excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
        $link    = get_permalink( $post );

        $subject = sprintf( __( 'New Community Blog Article: %s', 'ansar-community-blog' ), $title );
        $message = sprintf(
            '<p>%1$s</p><p>%2$s</p><p><a href="%3$s">%4$s</a></p>',
            esc_html__( 'A new community blog article has been published on the Majlis Ansarullah USA website.', 'ansar-community-blog' ),
            esc_html( $excerpt ),
            esc_url( $link ),
            esc_html__( 'Read the full article', 'ansar-community-blog' )
        );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        foreach ( $subscribers as $email ) {
            wp_mail( $email, $subject, $message, $headers );
        }
    }
    public static function enqueue_frontend_assets() {
        if ( ! is_page() && ! is_singular() ) {
            return;
        }

        $post_id      = get_queried_object_id();
        $post_content = $post_id ? get_post_field( 'post_content', $post_id ) : '';
        if ( ! $post_content || ( ! has_shortcode( $post_content, 'ansar_blog' ) && ! has_shortcode( $post_content, 'ansar_blog_all_categories' ) ) ) {
            return;
        }

        wp_enqueue_script( 'tailwind', 'https://cdn.tailwindcss.com', [], null, true );
        $tailwind_config = "tailwind.config = {theme: {extend: {colors: {teal: {50: '#f0fdfa',100: '#ccfbf1',200: '#99f6e4',300: '#5eead4',400: '#2dd4bf',500: '#14b8a6',600: '#0d9488',700: '#0f766e',800: '#115e59',900: '#134e4a'}},fontFamily: {serif: ['Georgia','Times New Roman','serif'],sans: ['Arial','Helvetica','sans-serif']}}}};";
        wp_add_inline_script( 'tailwind', $tailwind_config, 'before' );

        wp_enqueue_style( 'ansar-community-blog', plugins_url( 'assets/css/front.css', __FILE__ ), [], '1.0.0' );
        wp_enqueue_script( 'ansar-community-blog', plugins_url( 'assets/js/front.js', __FILE__ ), [], '1.0.0', true );

        $localize = [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'ansar_blog_frontend' ),
            'loadMoreStep' => 2,
            'maxArticles'  => 6,
            'texts'        => [
                'loading'             => __( 'Loading…', 'ansar-community-blog' ),
                'noResults'           => __( 'No matching articles found.', 'ansar-community-blog' ),
                'invalidEmail'        => __( 'Please enter a valid email address.', 'ansar-community-blog' ),
                'subscriptionUpdated' => __( 'Subscription updated.', 'ansar-community-blog' ),
            ],
        ];
        wp_localize_script( 'ansar-community-blog', 'ansarBlog', $localize );
    }

    public static function render_blog_shortcode() {
        ob_start();

        $featured_article = self::get_featured_article();
        $featured_id = $featured_article ? $featured_article->ID : 0;

        $recent_articles = self::get_recent_articles( 2, [ $featured_id ] );
        $total_recent    = self::count_articles_excluding( [ $featured_id ] );
        $remaining       = max( 0, min( 6, $total_recent ) - count( $recent_articles ) );

        $top_categories = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 5,
        ] );

        $all_categories_page = get_page_by_path( self::CATEGORIES_PAGE_SLUG );
        $categories_link     = $all_categories_page ? get_permalink( $all_categories_page ) : home_url();
        ?>
        <div class="ansar-blog-wrapper">
            <div class="ansar-blog-hero">
                <section class="bg-white shadow-soft">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 font-serif mb-2"><?php esc_html_e( 'Ansar Blog', 'ansar-community-blog' ); ?></h2>
                            <p class="text-xl text-gray-600"><?php esc_html_e( 'Stories, insights, and updates from our community', 'ansar-community-blog' ); ?></p>
                        </div>
                        <?php if ( $featured_article ) :
                            $featured_image = self::get_article_image( $featured_article->ID, 'large', true );
                            $featured_terms = get_the_terms( $featured_article, self::TAXONOMY );
                            $featured_label = $featured_terms ? $featured_terms[0]->name : __( 'Featured', 'ansar-community-blog' );
                            ?>
                            <div class="bg-gray-50 rounded-2xl overflow-hidden shadow-soft hover:shadow-lg transition-shadow">
                                <div class="md:flex">
                                    <div class="md:w-1/2">
                                        <?php if ( $featured_image ) : ?>
                                            <div class="h-64 md:h-full">
                                                <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( get_the_title( $featured_article ) ); ?>" class="object-cover w-full h-full">
                                            </div>
                                        <?php else : ?>
                                            <div class="h-64 md:h-full bg-gradient-to-br from-teal-600 to-teal-700 flex items-center justify-center">
                                                <div class="text-center text-white p-8">
                                                    <svg class="w-16 h-16 mx-auto mb-4 opacity-75" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                    </svg>
                                                    <h3 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Featured Article', 'ansar-community-blog' ); ?></h3>
                                                    <p class="text-teal-100"><?php echo esc_html( $featured_label ); ?></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="md:w-1/2 p-8">
                                        <div class="flex items-center text-sm text-gray-500 mb-3">
                                            <span class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-xs font-medium mr-3"><?php esc_html_e( 'Featured', 'ansar-community-blog' ); ?></span>
                                            <span><?php echo esc_html( get_the_date( '', $featured_article ) ); ?></span>
                                        </div>
                                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4 font-serif leading-tight">
                                            <a href="<?php echo esc_url( get_permalink( $featured_article ) ); ?>"><?php echo esc_html( get_the_title( $featured_article ) ); ?></a>
                                        </h3>
                                        <p class="text-gray-600 text-large mb-6 leading-relaxed"><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_article ), 40, '…' ) ); ?></p>
                                        <a class="inline-flex items-center bg-teal-600 hover:bg-teal-700 text-white px-6 py-3 rounded-lg font-medium transition-colors text-large" href="<?php echo esc_url( get_permalink( $featured_article ) ); ?>">
                                            <?php esc_html_e( 'Read Full Article', 'ansar-community-blog' ); ?>
                                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else : ?>
                            <p class="text-center text-gray-500"><?php esc_html_e( 'No featured article is available yet. Publish an article and mark it as featured to highlight it here.', 'ansar-community-blog' ); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="flex items-center justify-between mb-8">
                            <h2 class="text-2xl font-bold text-gray-900 font-serif" data-ansar-recent-heading><?php esc_html_e( 'Recent Articles', 'ansar-community-blog' ); ?></h2>
                        </div>

                        <div class="ansar-blog-recent" data-featured-id="<?php echo esc_attr( $featured_id ); ?>" data-loaded="<?php echo esc_attr( count( $recent_articles ) ); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8" id="ansar-blog-articles">
                                <?php
                                if ( $recent_articles ) {
                                    foreach ( $recent_articles as $article ) {
                                        echo self::get_article_card_html( $article );
                                    }
                                } else {
                                    echo '<p class="col-span-2 text-gray-500">' . esc_html__( 'No recent articles available.', 'ansar-community-blog' ) . '</p>';
                                }
                                ?>
                            </div>
                            <?php if ( $remaining > 0 ) : ?>
                                <div class="text-center">
                                    <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-8 py-3 rounded-lg font-medium transition-colors text-large" data-ansar-load-more data-remaining="<?php echo esc_attr( $remaining ); ?>">
                                        <?php esc_html_e( 'Load More Articles', 'ansar-community-blog' ); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="ansar-blog-search-results" class="hidden">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-900 font-serif"><?php esc_html_e( 'Search Results', 'ansar-community-blog' ); ?></h3>
                                <button type="button" class="text-gray-400 hover:text-gray-600" data-ansar-search-close aria-label="<?php esc_attr_e( 'Close search results', 'ansar-community-blog' ); ?>">&times;</button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4" data-ansar-search-container></div>
                        </div>
                    </div>

                    <aside class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-soft p-6 mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 font-serif"><?php esc_html_e( 'Search Articles', 'ansar-community-blog' ); ?></h3>
                            <form class="ansar-blog-search" novalidate>
                                <div class="relative">
                                    <input type="text" name="s" placeholder="<?php esc_attr_e( 'Search blog posts…', 'ansar-community-blog' ); ?>" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none text-large">
                                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-teal-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-white rounded-xl shadow-soft p-6 mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 font-serif border-b-2 border-teal-600 pb-2"><?php esc_html_e( 'Categories', 'ansar-community-blog' ); ?></h3>
                            <ul class="space-y-3">
                                <?php if ( $top_categories ) :
                                    foreach ( $top_categories as $category ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="flex justify-between items-center text-gray-700 hover:text-teal-600 transition-colors text-large py-1">
                                                <span><?php echo esc_html( $category->name ); ?></span>
                                                <span class="text-sm bg-gray-100 px-2 py-1 rounded-full"><?php echo esc_html( $category->count ); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach;
                                else : ?>
                                    <li class="text-gray-500"><?php esc_html_e( 'No categories yet.', 'ansar-community-blog' ); ?></li>
                                <?php endif; ?>
                                <li>
                                    <a href="<?php echo esc_url( $categories_link ); ?>" class="flex justify-between items-center text-teal-600 hover:text-teal-700 transition-colors text-large py-1 font-medium">
                                        <span><?php esc_html_e( 'View All Categories →', 'ansar-community-blog' ); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-white rounded-xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 font-serif"><?php esc_html_e( 'Stay Updated', 'ansar-community-blog' ); ?></h3>
                            <p class="text-gray-700 mb-4 text-large"><?php esc_html_e( 'Subscribe to receive our latest blog posts and community updates.', 'ansar-community-blog' ); ?></p>
                            <form class="ansar-blog-subscribe" novalidate>
                                <div class="space-y-3">
                                    <input type="email" name="email" placeholder="<?php esc_attr_e( 'Enter your email address', 'ansar-community-blog' ); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none text-large" required>
                                    <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white px-4 py-3 rounded-lg font-medium transition-colors text-large">
                                        <?php esc_html_e( 'Subscribe to Blog', 'ansar-community-blog' ); ?>
                                    </button>
                                    <p class="text-sm text-gray-500" data-ansar-subscribe-message></p>
                                </div>
                            </form>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }
    private static function get_article_card_html( WP_Post $article ) {
        $image   = self::get_article_image( $article->ID, 'medium_large' );
        $terms   = get_the_terms( $article, self::TAXONOMY );
        $term    = $terms ? $terms[0]->name : __( 'Community', 'ansar-community-blog' );
        $excerpt = wp_trim_words( get_the_excerpt( $article ), 30, '…' );
        ob_start();
        ?>
        <article class="bg-white rounded-xl shadow-soft overflow-hidden hover:shadow-lg transition-shadow ansar-blog-card">
            <?php if ( $image ) : ?>
                <div class="h-48">
                    <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $article ) ); ?>" class="object-cover w-full h-full">
                </div>
            <?php else : ?>
                <div class="h-48 bg-gradient-to-r from-teal-500 to-teal-600"></div>
            <?php endif; ?>
            <div class="p-6">
                <div class="flex items-center text-sm text-gray-500 mb-3">
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs mr-3"><?php echo esc_html( $term ); ?></span>
                    <span><?php echo esc_html( get_the_date( '', $article ) ); ?></span>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3 font-serif leading-tight hover:text-teal-600 transition-colors">
                    <a href="<?php echo esc_url( get_permalink( $article ) ); ?>"><?php echo esc_html( get_the_title( $article ) ); ?></a>
                </h3>
                <p class="text-gray-600 mb-4 leading-relaxed"><?php echo esc_html( $excerpt ); ?></p>
                <a class="inline-flex items-center text-teal-600 hover:text-teal-700 font-medium transition-colors" href="<?php echo esc_url( get_permalink( $article ) ); ?>">
                    <?php esc_html_e( 'Read More', 'ansar-community-blog' ); ?>
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function get_article_image( $post_id, $size = 'medium', $featured_context = false ) {
        $attached_meta = (int) get_post_meta( $post_id, self::ATTACHED_IMAGE_META, true );
        $thumbnail_url = '';

        if ( $featured_context && $attached_meta ) {
            $thumbnail_url = wp_get_attachment_image_url( $attached_meta, $size );
        }

        if ( ! $thumbnail_url ) {
            $thumbnail_url = get_the_post_thumbnail_url( $post_id, $size );
        }

        if ( ! $thumbnail_url ) {
            $thumbnail_url = self::extract_first_image_from_content( get_post_field( 'post_content', $post_id ) );
        }

        return $thumbnail_url;
    }

    private static function extract_first_image_from_content( $content ) {
        if ( empty( $content ) ) {
            return '';
        }

        preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches );
        return isset( $matches[1] ) ? esc_url_raw( $matches[1] ) : '';
    }

    private static function get_featured_article() {
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_key'       => self::FEATURED_META_KEY,
            'meta_value'     => 1,
        ];

        $query = new WP_Query( $args );
        if ( $query->have_posts() ) {
            return $query->posts[0];
        }

        $fallback = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        return $fallback->have_posts() ? $fallback->posts[0] : null;
    }

    private static function get_recent_articles( $count = 2, $exclude = [] ) {
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $count,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post__not_in'   => array_filter( array_map( 'intval', $exclude ) ),
        ];

        return get_posts( $args );
    }

    private static function count_articles_excluding( $exclude = [] ) {
        $args = [
            'post_type'      => self::POST_TYPE,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'post__not_in'   => array_filter( array_map( 'intval', $exclude ) ),
        ];

        $query = new WP_Query( $args );
        return $query->post_count;
    }

    public static function ajax_load_more_articles() {
        check_ajax_referer( 'ansar_blog_frontend', 'nonce' );

        $offset      = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $featured_id = isset( $_POST['featured'] ) ? intval( $_POST['featured'] ) : 0;
        $limit       = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 2;

        $articles = get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'offset'         => $offset,
            'post__not_in'   => array_filter( [ $featured_id ] ),
        ] );

        $html = '';
        foreach ( $articles as $article ) {
            $html .= self::get_article_card_html( $article );
        }

        wp_send_json_success( [
            'html'   => $html,
            'count'  => count( $articles ),
        ] );
    }

    public static function ajax_search_articles() {
        check_ajax_referer( 'ansar_blog_frontend', 'nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        if ( empty( $term ) ) {
            wp_send_json_success( [ 'html' => '', 'count' => 0 ] );
        }

        $query = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            's'              => $term,
            'posts_per_page' => 6,
        ] );

        $html = '';
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $article ) {
                $html .= self::get_article_card_html( $article );
            }
        }

        wp_send_json_success( [
            'html'  => $html,
            'count' => $query->post_count,
        ] );
    }

    public static function ajax_subscribe() {
        check_ajax_referer( 'ansar_blog_frontend', 'nonce' );

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if ( ! $email || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'ansar-community-blog' ) ] );
        }

        $subscribers = get_option( self::SUBSCRIBERS_OPTION, [] );
        if ( ! is_array( $subscribers ) ) {
            $subscribers = [];
        }

        if ( in_array( $email, $subscribers, true ) ) {
            wp_send_json_success( [ 'message' => __( 'You are already subscribed to the community blog.', 'ansar-community-blog' ) ] );
        }

        $subscribers[] = $email;
        update_option( self::SUBSCRIBERS_OPTION, $subscribers );

        wp_send_json_success( [ 'message' => __( 'Thank you for subscribing! You will receive an email when new articles are published.', 'ansar-community-blog' ) ] );
    }
    public static function render_all_categories_shortcode() {
        $categories = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        ob_start();
        ?>
        <div class="ansar-blog-all-categories max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h2 class="text-3xl font-bold text-gray-900 font-serif mb-6"><?php esc_html_e( 'All Blog Categories', 'ansar-community-blog' ); ?></h2>
            <?php if ( $categories ) : ?>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ( $categories as $category ) : ?>
                        <li class="bg-white rounded-xl shadow-soft p-6 flex justify-between items-center">
                            <div>
                                <a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="text-xl font-semibold text-gray-900 hover:text-teal-600 transition-colors"><?php echo esc_html( $category->name ); ?></a>
                                <?php if ( $category->description ) : ?>
                                    <p class="text-gray-600 mt-2"><?php echo esc_html( $category->description ); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm bg-gray-100 px-3 py-1 rounded-full text-gray-700"><?php printf( _n( '%s Article', '%s Articles', $category->count, 'ansar-community-blog' ), number_format_i18n( $category->count ) ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="text-gray-500"><?php esc_html_e( 'There are no blog categories yet.', 'ansar-community-blog' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function on_activate() {
        self::register_post_type_and_taxonomy();
        flush_rewrite_rules();

        self::maybe_create_page( __( 'Community Blog', 'ansar-community-blog' ), self::BLOG_PAGE_SLUG, '[ansar_blog]' );
        self::maybe_create_page( __( 'Blog Categories', 'ansar-community-blog' ), self::CATEGORIES_PAGE_SLUG, '[ansar_blog_all_categories]' );
    }

    private static function maybe_create_page( $title, $slug, $shortcode ) {
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            return;
        }

        wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $shortcode,
        ] );
    }
}

Ansar_Community_Blog::init();
