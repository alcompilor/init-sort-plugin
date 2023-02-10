<?php

// Function to sort products by variations stock count/status
add_filter('woocommerce_get_catalog_ordering_args', 'first_sort_by_stock_amount', 9);

function first_sort_by_stock_amount($args)
{
	$args = [
		'post_type' => 'product',
		'posts' => -1,
	];
	$args['orderby'] = 'meta_value_num';
	$args['order'] = 'DESC';
	$args['meta_key'] = 'p_total_stock';
	return $args;
}

// Init cron job to update variations total stock - once every 24 hours
add_action('wp', 'update_v_setup_schedule');
function update_v_setup_schedule()
{
	if (!wp_next_scheduled('update_v_daily_event')) {
		wp_schedule_event(time(), 'daily', 'update_v_daily_event');
	}
}

add_action('update_v_daily_event', 'update_v_stock');

// Function to fire an async scheduled action
function update_v_stock()
{
	// getting all products
	$products_ids = get_posts(
		array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'fields' => 'ids',
			'numberposts' => -1
		)
	);

	foreach ($products_ids as $product_id) {
		as_enqueue_async_action('update_v_stock', array($product_id), 'update_v_stock_job');
	}
}


// Function that loops over all products and updates their total variation stock count
add_action('update_v_stock', 'update_stock_variations', 10, 3);
function update_stock_variations($product_id)
{

	// Get the WC_Product object
	$product = wc_get_product($product_id);
	$stock = 0;
	if ($product->is_type('variable')) {
		$variations = $product->get_available_variations();
		foreach ($variations as $variation) {
			$variation_id = $variation['variation_id'];
			$variation_obj = new WC_Product_variation($variation_id);
			$stock += $variation_obj->get_stock_quantity();
			update_post_meta($variation_id, '_manage_stock', 'yes');
			update_post_meta($product_id, '_manage_stock', 'no');
		}
	} else {
		$stock += $product->get_stock_quantity();
		update_post_meta($product_id, '_manage_stock', 'yes');
	}

	$product->update_meta_data('p_total_stock', $stock);
	$product->save_meta_data();

}


// Function that forces a new or modified product to reset variation stock count
add_action('save_post_product', 'add_p_total_meta_for_new_products');
function add_p_total_meta_for_new_products($post_id)
{
	$product = wc_get_product($post_id);

	$product->update_meta_data('p_total_stock', 0);
	$product->save_meta_data();
}



/*********************** DEBUGGING SECTION ************************/
// Function to display stock count regardless of variation - ONLY USED FOR DEBUGGING
/*
add_action('woocommerce_single_product_summary', 'get_product_info', 1000);

function get_product_info() {
	
	global $product;
	$_product = get_post_meta( $product->get_id() );
	echo "<script>console.log(' " . json_encode($_product) . "' );</script>";
	
}
*/
/*********************** DEBUGGING SECTION ************************/