<?php
add_action( 'wp_enqueue_scripts', function() {	
	wp_enqueue_style( '20-23-css', get_template_directory_uri() . '/style.css' );

    wp_enqueue_style( 'bootstrap-css', 
        get_stylesheet_directory_uri() . '/vendors/bootstrap/css/bootstrap.min.css' );
	
	wp_enqueue_style( "20-23-child-css",
        get_stylesheet_directory_uri() . '/style.css' );

	wp_enqueue_script( 'bootstrap-js', 
        get_stylesheet_directory_uri() . '/vendors/bootstrap/js/bootstrap.bundle.min.js', 
					  array('jquery') );
	
	wp_enqueue_script( '20-23-child-js', get_stylesheet_directory_uri() . '/script.js', 
					  array('jquery', 'bootstrap-js') );
} );

add_action( 'after_setup_theme', function() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'block-template-parts' );
} );

add_filter( 'register_block_type_args', function( $args, $name ) {
    if ( $name == 'core/latest-posts' ) {
        $args['render_callback'] = 'modify_core_latest_posts'; //call custom gutenberg block
    }
    return $args;
}, 10, 3 );

function modify_core_latest_posts($attributes) {
    global $post, $block_core_latest_posts_excerpt_length;

    $args = array(
        'posts_per_page'      => $attributes['postsToShow'],
        'post_status'         => 'publish',
        'order'               => $attributes['order'],
        'orderby'             => $attributes['orderBy'],
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );

    $block_core_latest_posts_excerpt_length = $attributes['excerptLength'];

    if ( isset( $attributes['categories'] ) ) {
        $args['category__in'] = array_column( $attributes['categories'], 'id' );
    }
    if ( isset( $attributes['selectedAuthor'] ) ) {
        $args['author'] = $attributes['selectedAuthor'];
    }

    $query        = new WP_Query;
    $recent_posts = $query->query( $args );

    if ( isset( $attributes['displayFeaturedImage'] ) && $attributes['displayFeaturedImage'] ) {
        update_post_thumbnail_cache( $query );
    }

    $list_items_markup = '';

    foreach ( $recent_posts as $post ) {
        $post_link = esc_url( get_permalink( $post ) );
        $title     = get_the_title( $post );

        if ( ! $title ) {
            $title = __( '(no title)' );
        }

        $list_items_markup .= '<li>';

        $real_new = get_post_meta(get_the_ID(), 'real_new');
        if($real_new) {
            if ($real_new[0]) {
                $list_items_markup .= "<span style='position:relative;top:0;left:0;z-index:99;'>" . $real_new[0] . "</span>";
            }
        }

        if ( $attributes['displayFeaturedImage'] && has_post_thumbnail( $post ) ) {
            $image_style = '';
            if ( isset( $attributes['featuredImageSizeWidth'] ) ) {
                $image_style .= sprintf( 'max-width:%spx;', $attributes['featuredImageSizeWidth'] );
            }
            if ( isset( $attributes['featuredImageSizeHeight'] ) ) {
                $image_style .= sprintf( 'max-height:%spx;', $attributes['featuredImageSizeHeight'] );
            }

            $image_classes = 'wp-block-latest-posts__featured-image';
            if ( isset( $attributes['featuredImageAlign'] ) ) {
                $image_classes .= ' align' . $attributes['featuredImageAlign'];
            }

            $featured_image = get_the_post_thumbnail(
                $post,
                $attributes['featuredImageSizeSlug'],
                array(
                    'style' => esc_attr( $image_style ),
                )
            );
            if ( $attributes['addLinkToFeaturedImage'] ) {
                $featured_image = sprintf(
                    '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                    esc_url( $post_link ),
                    esc_attr( $title ),
                    $featured_image
                );
            }
            $list_items_markup .= sprintf(
                '<figure class="%1$s">%2$s</figure>',
                esc_attr( $image_classes ),
                $featured_image
            );
        }

		$list_items_markup .= '<aside>';
		if ( !empty( $post_categories = get_the_category() ) ) {
// 			$post_categories = array_column( $post_categories_array, 'name' );
			$category_links = array_map( function( $category ) {
				return "<a href='" . esc_url( get_category_link( $category ) ) . "'>" . esc_html( $category->name ) . "</a>";
			}, $post_categories );
			
			$list_items_markup .= sprintf(
				'<div class="ws-label">%1$s</div>',
				implode( ', ', $category_links )
			);
	
		}
					
		$list_items_markup .= sprintf(
            '<h1><a class="wp-block-latest-posts__post-title" href="%1$s">%2$s</a></h1>',
            esc_url( $post_link ),
            $title
        );

		$list_items_markup .= '<div class="post-attributes">';
        if ( isset( $attributes['displayPostDate'] ) && $attributes['displayPostDate'] ) {
            $list_items_markup .= sprintf(
                '<time datetime="%1$s" class="wp-block-latest-posts__post-date">%2$s %3$s</time>',
                esc_attr( get_the_date( 'c', $post ) ),
                esc_html__( 'Added' ),
                get_the_date( '', $post )
            );
        }

        if ( comments_open( $post ) ) {
            $list_items_markup .= sprintf(
                '<div class="wp-block-latest-posts__post-comments">
					<svg class="ws-svg" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1.25 3.125V13.125H3.75V16.3086L4.76562 15.4883L7.71484 13.125H13.75V3.125H1.25ZM2.5 4.375H12.5V11.875H7.28516L7.10938 12.0117L5 13.6914V11.875H2.5V4.375ZM15 5.625V6.875H17.5V14.375H15V16.1914L12.7148 14.375H8.02734L6.46484 15.625H12.2852L16.25 18.8086V15.625H18.75V5.625H15Z"/>
</svg>
					%1$s
				</div>',
                intval( get_comments_number( $post ) ) 
			);
        }
		
		$list_items_markup .= '</div>';

		if ( isset( $attributes['displayPostContent'] ) && $attributes['displayPostContent']
            && isset( $attributes['displayPostContentRadio'] ) && 'excerpt' === $attributes['displayPostContentRadio'] ) {

            $trimmed_excerpt = get_the_excerpt( $post );

            if ( post_password_required( $post ) ) {
                $trimmed_excerpt = __( 'This content is password protected.' );
            }

            $list_items_markup .= sprintf(
                '<div class="ws-post-excerpt wp-block-latest-posts__post-excerpt">%1$s</div>',
                $trimmed_excerpt
            );
        }

        if ( isset( $attributes['displayPostContent'] ) && $attributes['displayPostContent']
            && isset( $attributes['displayPostContentRadio'] ) && 'full_post' === $attributes['displayPostContentRadio'] ) {

            $post_content = html_entity_decode( $post->post_content, ENT_QUOTES, get_option( 'blog_charset' ) );

            if ( post_password_required( $post ) ) {
                $post_content = __( 'This content is password protected.' );
            }

            $list_items_markup .= sprintf(
                '<div class="wp-block-latest-posts__post-full-content">%1$s</div>',
                wp_kses_post( $post_content )
            );
        }

		if ( isset( $attributes['displayAuthor'] ) && $attributes['displayAuthor'] ) {
			$author_display_name = get_the_author_meta( 'display_name', $post->post_author );

			if ( ! empty( $author_display_name ) ) {
				$user_avatar = get_avatar( $post->post_author, 24 );
					
				$list_items_markup .= sprintf(
					'<div class="wp-block-latest-posts__post-author ws-post-author">%1$s %2$s</div>',
					$user_avatar,
					$author_display_name
				);
			}
		}
		
        $list_items_markup .= "</aside></li>\n";
    }

    remove_filter( 'excerpt_length', 'block_core_latest_posts_get_excerpt_length', 20 );

    $class = 'wp-block-latest-posts__list';

    if ( isset( $attributes['postLayout'] ) && 'grid' === $attributes['postLayout'] ) {
        $class .= ' is-grid';
    }

    if ( isset( $attributes['columns'] ) && 'grid' === $attributes['postLayout'] ) {
        $class .= ' columns-' . $attributes['columns'];
    }

    if ( isset( $attributes['displayPostDate'] ) && $attributes['displayPostDate'] ) {
        $class .= ' has-dates';
    }

    if ( isset( $attributes['displayAuthor'] ) && $attributes['displayAuthor'] ) {
        $class .= ' has-author';
    }

    $wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $class ) );

	$all_posts = wp_count_posts();
	$more_button = $all_posts->publish <= $attributes['postsToShow'] ? ''
		: "<a class='show-all-posts' href=" . add_query_arg( 'post_type', 'post ', home_url() ) . ">" . esc_html__( 'All posts' ) . "</a>";
		
    return "<ul $wrapper_attributes>$list_items_markup</ul>$more_button";
}