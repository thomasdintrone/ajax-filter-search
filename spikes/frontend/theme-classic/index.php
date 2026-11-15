<?php
// Minimal classic theme used only by the spikes.
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php wp_title( '|', true, 'right' ); bloginfo( 'name' ); ?></title><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<header class="wrap"><h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></h1></header>
<main class="wrap"><?php while ( have_posts() ) : the_post(); ?><article><h2><?php the_title(); ?></h2><?php the_content(); ?></article><?php endwhile; ?></main>
<footer class="wrap"><p>Footer</p></footer><?php wp_footer(); ?></body></html>
