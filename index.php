<?php
/*
Plugin Name: AnonyEngine ratings
Description: Adds a star rating system to WordPress comments
Version: 1.0.0
Author: Makiomar
Author URI: https://makiomar.com/
Text Domain: anony-ratings
*/

/**
 * Holds plugin text domain
 * @const
 */
define('ANORT_TEXTDOM', 'anony-ratings');


/**
 * Holds plugin uri
 * @const
 */
define('ANORT_URI', plugin_dir_url( __FILE__ ));

/**
 * Holds plugin PATH
 * @const
 */ 
define('ANORT_DIR', wp_normalize_path(plugin_dir_path( __FILE__ )));

/**
 * Load plugin textdomain.
 */
add_action( 'init', function () {
  load_plugin_textdomain( ANORT_TEXTDOM , false, ANORT_DIR . 'languages' ); 
} );
  


//Enqueue the plugin's styles.
add_action( 'wp_enqueue_scripts', function () {

	wp_register_style( 'anony-comment-rating-styles', plugins_url( '/', __FILE__ ) . 'assets/css/style.css' );

	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'anony-comment-rating-styles' );
} );


//Create the rating interface.
add_action( 'comment_form_logged_in_after', 'anony_comment_rating_rating_field' );
add_action( 'comment_form_after_fields', 'anony_comment_rating_rating_field' );
function anony_comment_rating_rating_field () {
	?>
	<label for="rating"><?= esc_html__( 'Rating', ANORT_TEXTDOM ) ?><span class="required">*</span></label>
	<fieldset class="comments-rating">
		<span class="rating-container">
			<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
				<input type="radio" id="rating-<?php echo esc_attr( $i ); ?>" name="rating" value="<?php echo esc_attr( $i ); ?>" /><label for="rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
			<?php endfor; ?>
			<input type="radio" id="rating-0" class="star-cb-clear" name="rating" value="0" /><label for="rating-0">0</label>
		</span>
	</fieldset>
	<?php
}

//Save the rating submitted by the user.
add_action( 'comment_post', function ( $comment_id ) {
	if ( ( isset( $_POST['rating'] ) ) && ( '' !== $_POST['rating'] ) )
	$rating = intval( $_POST['rating'] );
	add_comment_meta( $comment_id, 'rating', $rating );
} );


//Make the rating required.
add_filter( 'preprocess_comment',  function ( $commentdata ) {
	if ( ! is_admin() && ( ! isset( $_POST['rating'] ) || 0 === intval( $_POST['rating'] ) ) )
	wp_die( esc_html__( 'Error: You did not add a rating. Hit the Back button on your Web browser and resubmit your comment with a rating.', ANORT_TEXTDOM ) );
	return $commentdata;
});


//Display the rating on a submitted comment.
add_filter( 'comment_text', function ( $comment_text ){

	if ( $rating = get_comment_meta( get_comment_ID(), 'rating', true ) ) {
		$stars = '<p class="stars">';
		for ( $i = 1; $i <= $rating; $i++ ) {
			$stars .= '<span class="dashicons dashicons-star-filled"></span>';
		}
		$stars .= '</p>';
		$comment_text = $comment_text . $stars;
		return $comment_text;
	} else {
		return $comment_text;
	}
});

function anony_reviews_count($id){
	$comments = get_approved_comments( $id );
	
	if ( $comments ) {
		$i = 0;
		foreach( $comments as $comment ){
			$rate = get_comment_meta( $comment->comment_ID, 'rating', true );
			if( isset( $rate ) && '' !== $rate ) {
				$i++;
			}
		}
	}
	
	return $i;
}

//Get the average rating of a post.
function anony_comment_rating_get_average_ratings( $id ) {
	$comments = get_approved_comments( $id );

	if ( $comments ) {
		$i = 0;
		$total = 0;
		foreach( $comments as $comment ){
			$rate = get_comment_meta( $comment->comment_ID, 'rating', true );
			if( isset( $rate ) && '' !== $rate ) {
				$i++;
				$total += $rate;
			}
		}

		if ( 0 === $i ) {
			return false;
		} else {
			return round( $total / $i, 1 );
		}
	} else {
		return false;
	}
}

function anony_get_ratings($id){
	
	if ( false === anony_comment_rating_get_average_ratings( $id ) ) {
		$stars = '<div class="no-rating">' . sprintf(esc_html__( 'Reviews (%d)', ANORT_TEXTDOM ), 0);
		for ( $i = 5; $i >= 1; $i-- ) : 
				$stars .= '<span style="overflow:hidden;" class="dashicons dashicons-star"></span>';
		endfor; 
		return $stars .'</div>';
	}
	
	$stars   = '';
	$average = anony_comment_rating_get_average_ratings( $id );
	$reviews_count = anony_reviews_count($id);

	for ( $i = 1; $i <= $average + 1; $i++ ) {
		
		$width = intval( $i - $average > 0 ? 20 - ( ( $i - $average ) * 20 ) : 20 );

		if ( 0 === $width ) {
			continue;
		}

		$stars .= '<span style="overflow:hidden; width:' . $width . 'px" class="dashicons dashicons-star-filled"></span>';

		if ( $i - $average > 0 ) {
			$stars .= '<span style="overflow:hidden; position:relative; left:-' . $width .'px;" class="dashicons dashicons-star-empty"></span>';
		}
	}
	
	$custom_content  = '<p class="average-rating">'.sprintf(esc_html__( 'Reviews (%d)', ANORT_TEXTDOM ), $reviews_count).' ' . $stars .'</p>';
	
	return $custom_content;
}
//Display the average rating above the content.
add_filter( 'the_content', function ( $content ) {

	global $post;
	
	$before_content = false;
	if(!$before_content) return $content;
	if ( false === anony_comment_rating_get_average_ratings( $post->ID ) ) {
		return $content;
	}
	
	$custom_content = anony_get_ratings($post->ID);
	
	$custom_content .= $content;
	return $custom_content;
} );

/**
 * Get comment form
 * @param  array $atts 
 */
function get_comment_form( $atts) {

	ob_start();
	echo '<div class="anony-ratings-form">';
	comment_form();
	echo '</div>';
	return ob_get_clean();
   
}

add_shortcode( 'anony-comment-form', 'get_comment_form' );


/**
 * Get comments list
 */
add_shortcode( 'anony-comments-list', function ( $atts) {
	
	$comments = get_comments( array('post_id' => get_the_id()) );
	
	$html = '<ol class="commentlist anony-commentlist">';
	$html .= wp_list_comments(['echo'=> false], $comments);
	$html .= '</ol>';

	return $html;
   
} );


/**
 * Get retings
 */

add_shortcode( 'anony-post-ratings', function( $atts) {
	
	
	return anony_get_ratings(get_the_ID());
   
} );
