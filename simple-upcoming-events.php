<?php
/**
 * Plugin Name: Simple Upcoming Events
 * Description: Add events (title, description, URL) and display the 3 most-recent in a dark, card-style list via [upcoming_events].
 * Version:     1.1
 * Author:      You
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) Register the Events CPT
function sue_register_cpt() {
  register_post_type( 'sue_event', [
    'labels' => [
      'name'          => 'Events',
      'singular_name' => 'Event',
      'add_new_item'  => 'Add New Event',
    ],
    'public'       => true,
    'has_archive'  => false,
    'show_in_menu' => true,
    'menu_icon'    => 'dashicons-calendar-alt',
    'supports'     => [ 'title' ],
  ]);
}
add_action( 'init', 'sue_register_cpt' );

// 2) Add Meta Boxes for Description + URL
function sue_add_meta_boxes() {
  add_meta_box( 'sue_event_details', 'Event Details', 'sue_render_meta_box', 'sue_event', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'sue_add_meta_boxes' );

function sue_render_meta_box( $post ) {
  wp_nonce_field( 'sue_save_details', 'sue_nonce' );
  $desc  = get_post_meta( $post->ID, '_sue_description', true );
  $url   = get_post_meta( $post->ID, '_sue_url', true );
  $start = get_post_meta( $post->ID, '_sue_start', true );
  $end   = get_post_meta( $post->ID, '_sue_end', true );
  ?>
  <p><label>Start Date &amp; Time:</label><br>
     <input type="datetime-local" name="sue_start" value="<?php echo esc_attr( $start ); ?>">
  </p>
  <p><label>End Date &amp; Time:</label><br>
     <input type="datetime-local" name="sue_end" value="<?php echo esc_attr( $end ); ?>">
  </p>
  <p><label>Description:</label><br>
     <textarea id="sue_description" name="sue_description" rows="4" maxlength="75" style="width:100%;"><?php echo esc_textarea( $desc ); ?></textarea><br>
     <span id="sue_desc_counter"></span>
  </p>
  <p><label>Link (URL):</label><br>
     <input type="url" name="sue_url" style="width:100%;" value="<?php echo esc_attr( $url ); ?>">
  </p>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var ta = document.getElementById('sue_description');
    var counter = document.getElementById('sue_desc_counter');
    var limit = 75;
    function update() {
      var len = ta.value.length;
      if (len >= limit) {
        counter.textContent = 'You have reached the maximum characters';
      } else {
        counter.textContent = (limit - len) + ' characters remaining';
      }
    }
    ta.addEventListener('input', update);
    update();
  });
  </script>
  <?php
}

// 3) Save Meta
function sue_save_event_details( $post_id ) {
  if ( ! isset( $_POST['sue_nonce'] ) ||
       ! wp_verify_nonce( $_POST['sue_nonce'], 'sue_save_details' ) ) {
    return;
  }

  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
    return;
  }

  if ( ! current_user_can( 'edit_post', $post_id ) ) {
    return;
  }

  $desc = isset( $_POST['sue_description'] )
    ? mb_substr( sanitize_textarea_field( $_POST['sue_description'] ), 0, 75 )
    : '';

  $url  = isset( $_POST['sue_url'] )
    ? esc_url_raw( $_POST['sue_url'] )
    : '';

  $start = isset( $_POST['sue_start'] )
    ? sanitize_text_field( $_POST['sue_start'] )
    : '';

  $end   = isset( $_POST['sue_end'] )
    ? sanitize_text_field( $_POST['sue_end'] )
    : '';

  update_post_meta( $post_id, '_sue_description', $desc );
  update_post_meta( $post_id, '_sue_url', $url );
  update_post_meta( $post_id, '_sue_start', $start );
  update_post_meta( $post_id, '_sue_end', $end );
}
add_action( 'save_post_sue_event', 'sue_save_event_details' );

// 4) Shortcode to Render 3 Most Recent
function sue_upcoming_events_shortcode( $atts ) {
  $atts = shortcode_atts( ['limit'=>3], $atts, 'upcoming_events' );
  $q = new WP_Query([
    'post_type'      => 'sue_event',
    'posts_per_page' => intval( $atts['limit'] ),
    'orderby'        => 'date',
    'order'          => 'DESC',
  ]);
  if ( ! $q->have_posts() ) return '<div class="sue-events-none">No upcoming events.</div>';
  ob_start(); ?>
  <div class="sue-events">
    <div class="sue-header">✕</div>
    <ul class="sue-list">
    <?php while( $q->have_posts() ) : $q->the_post();
      $desc  = get_post_meta( get_the_ID(), '_sue_description', true );
      $url   = get_post_meta( get_the_ID(), '_sue_url', true ) ?: get_permalink();
      $start = get_post_meta( get_the_ID(), '_sue_start', true );
      $end   = get_post_meta( get_the_ID(), '_sue_end', true );
    ?>
      <li class="sue-item">
        <a href="<?php echo esc_url( $url ); ?>" class="sue-title"><?php the_title(); ?></a>
        <?php if ( $start || $end ): ?>
          <div class="sue-dates">
            <?php if ( $start ): ?><span class="sue-start"><?php echo esc_html( $start ); ?></span><?php endif; ?>
            <?php if ( $end ): ?><span class="sue-end"> - <?php echo esc_html( $end ); ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ( $desc ): ?>
          <div class="sue-desc"><?php echo esc_html( $desc ); ?></div>
        <?php endif; ?>
      </li>
    <?php endwhile; wp_reset_postdata(); ?>
    </ul>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode( 'upcoming_events', 'sue_upcoming_events_shortcode' );
