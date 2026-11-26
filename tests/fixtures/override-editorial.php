<?php
// Test fixture: stands in for your-theme/afs-template/editorial.php.
defined( 'ABSPATH' ) || exit;
echo 'OVERRIDDEN-EDITORIAL ' . esc_html( get_the_title() ) . ' ' . ( isset( $afs_options['columns'] ) ? 'opts' : 'noopts' );
