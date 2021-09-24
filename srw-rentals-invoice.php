<?php
/**
 * SRW Rental Invoices
 *
 * @package SRW Rental Invoices
 * @author Joe Badaczewski
 * @license GPL-2.0+
 * @link https://sapphireroadweddings.com
 * @copyright 2021 Sapphire Road Weddings
 *
 *            @wordpress-plugin
 *            Plugin Name: SRW Rental Invoices
 *            Plugin URI: https://sapphireroadweddings.com/
 *            Description: Make invoices for SRW Rentals
 *            Version: 0.0.1
 *            Author: Joe Badaczewski
 *            Author URI: https://joebad.com/
 *            Text Domain: srw-rential-invoices
 *            License: GPL-2.0+
 *            License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

add_filter('the_content','rei_add_to_cart_button', 20,1);
function rei_add_to_cart_button($content){
	global $post;
	if ($post->post_type !== 'rental') {return $content; }
	
	ob_start();
	?>
	<form enctype="multipart/form-data" action="" method="post">
		<input name="add-to-cart" type="hidden" value="<?php echo $post->ID ?>" />
		<input name="quantity" type="number" value="1" min="1"  />
		<input type="submit" value="Add to cart" />
	</form>
	<?php
	
	return $content . ob_get_clean();
}

add_action( 'init', 'register_myclass' );
function register_myclass() {
    class IA_Woo_Product extends WC_Product  {

        protected $post_type = 'rental';
    
        public function get_type() {
            return 'rental';
        }
    
        public function __construct( $product = 0 ) {
            $this->supports[]   = 'ajax_add_to_cart';
    
            parent::__construct( $product );
    
    
        }
        // maybe overwrite other functions from WC_Product
    
    }
    
    class IA_Data_Store_CPT extends WC_Product_Data_Store_CPT {
    
        public function read( &$product ) { // this is required
            $product->set_defaults();
            $post_object = get_post( $product->get_id() );
    
            if ( ! $product->get_id() || ! $post_object || 'rental' !== $post_object->post_type ) {
    
                throw new Exception( __( 'Invalid product.', 'woocommerce' ) );
            }
    
            $product->set_props(
                array(
                    'name'              => $post_object->post_title,
                    'slug'              => $post_object->post_name,
                    'date_created'      => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
                    'date_modified'     => 0 < $post_object->post_modified_gmt ? wc_string_to_timestamp( $post_object->post_modified_gmt ) : null,
                    'status'            => $post_object->post_status,
                    'description'       => $post_object->post_content,
                    'short_description' => $post_object->post_excerpt,
                    'parent_id'         => $post_object->post_parent,
                    'menu_order'        => $post_object->menu_order,
                    'reviews_allowed'   => 'open' === $post_object->comment_status,
                )
            );
    
            $this->read_attributes( $product );
            $this->read_downloads( $product );
            $this->read_visibility( $product );
            $this->read_product_data( $product );
            $this->read_extra_data( $product );
            $product->set_object_read( true );
        }
    
        // maybe overwrite other functions from WC_Product_Data_Store_CPT
    
    }
    
    
    class IA_WC_Order_Item_Product extends WC_Order_Item_Product {
        public function set_product_id( $value ) {
            if ( $value > 0 && 'rental' !== get_post_type( absint( $value ) ) ) {
                $this->error( 'order_item_product_invalid_product_id', __( 'Invalid product ID', 'woocommerce' ) );
            }
            $this->set_prop( 'product_id', absint( $value ) );
        }
    
    }
    
    
    
    
    function IA_woocommerce_data_stores( $stores ) {
        // the search is made for product-$post_type so note the required 'product-' in key name
        $stores['product-rental'] = 'IA_Data_Store_CPT';
        return $stores;
    }
    add_filter( 'woocommerce_data_stores', 'IA_woocommerce_data_stores' , 11, 1 );
    
    
    function IA_woo_product_class( $class_name ,  $product_type ,  $product_id ) {
        if ($product_type == 'rental')
            $class_name = 'IA_Woo_Product';
        return $class_name; 
    }
    add_filter('woocommerce_product_class','IA_woo_product_class',25,3 );
    
    
    
    function my_woocommerce_product_get_price( $price, $product ) {

        if ($product->get_type() === 'rental'){
            $attributes = get_post_meta($product->get_id(), "rentals-attributes-list", true);
            foreach($attributes as $attribute) {
                $attribute = explode(" | ", $attribute['rental-attribute']);
                if ($attribute[0] === 'Price'){
                    $price = json_decode($attribute[1]);
                }
            }
        }
        return $price;
    }
    add_filter('woocommerce_get_price','my_woocommerce_product_get_price',20,2);
    add_filter('woocommerce_product_get_price', 'my_woocommerce_product_get_price', 10, 2 );
    
    
    
    // required function for allowing posty_type to be added; maybe not the best but it works
    function IA_woo_product_type($false,$product_id) { 
        if ($false === false) { // don't know why, but this is how woo does it
            global $post;
            // maybe redo it someday?!
            if (is_object($post) && !empty($post)) { // post is set
                if ($post->post_type == 'rental' && $post->ID == $product_id) 
                    return 'rental';
                else {
                    $product = get_post( $product_id );
                    if (is_object($product) && !is_wp_error($product)) { // post not set but it's a rental
                        if ($product->post_type == 'rental') 
                            return 'rental';
                    } // end if 
                }    
    
            } else if(wp_doing_ajax()) { // has post set (usefull when adding using ajax)
                $product_post = get_post( $product_id );
                if ($product_post->post_type == 'rental') 
                    return 'rental';
            } else { 
                $product = get_post( $product_id );
                if (is_object($product) && !is_wp_error($product)) { // post not set but it's a rental
                    if ($product->post_type == 'rental') 
                        return 'rental';
                } // end if 
    
            } // end if  // end if 
    
    
    
        } // end if 
        return false;
    }
    add_filter('woocommerce_product_type_query','IA_woo_product_type',12,2 );
    
    function IA_woocommerce_checkout_create_order_line_item_object($item, $cart_item_key, $values, $order) {
    
        $product                    = $values['data'];
        if ($product->get_type() == 'rental') {
            return new IA_WC_Order_Item_Product();
        } // end if 
        return $item ;
    }   
    add_filter( 'woocommerce_checkout_create_order_line_item_object', 'IA_woocommerce_checkout_create_order_line_item_object', 20, 4 );
    
    function cod_woocommerce_checkout_create_order_line_item($item,$cart_item_key,$values,$order) {
        if ($values['data']->get_type() == 'rental') {
            $item->update_meta_data( '_rental', 'yes' ); // add a way to recognize custom post type in ordered items
            return;
        } // end if 
    
    }
    add_action( 'woocommerce_checkout_create_order_line_item', 'cod_woocommerce_checkout_create_order_line_item', 20, 4 );
    
    function IA_woocommerce_get_order_item_classname($classname, $item_type, $id) {
        global $wpdb;
        $is_IA = $wpdb->get_var("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = {$id} AND meta_key = '_rental'");
    
    
        if ('yes' === $is_IA) { // load the new class if the item is our custom post
            $classname = 'IA_WC_Order_Item_Product';
        } // end if 
        return $classname;
    }
    add_filter( 'woocommerce_get_order_item_classname', 'IA_woocommerce_get_order_item_classname', 20, 3 );
}

function wc_remove_checkout_fields( $fields ) {

    // Billing fields
    unset( $fields['billing']['billing_company'] );
    unset( $fields['billing']['billing_email'] );
    unset( $fields['billing']['billing_phone'] );
    unset( $fields['billing']['billing_state'] );
    unset( $fields['billing']['billing_first_name'] );
    unset( $fields['billing']['billing_last_name'] );
    unset( $fields['billing']['billing_address_1'] );
    unset( $fields['billing']['billing_address_2'] );
    unset( $fields['billing']['billing_city'] );
    unset( $fields['billing']['billing_country'] );
    unset( $fields['billing']['billing_postcode'] );

    // Shipping fields
    unset( $fields['shipping']['shipping_company'] );
    unset( $fields['shipping']['shipping_phone'] );
    unset( $fields['shipping']['shipping_state'] );
    unset( $fields['shipping']['shipping_first_name'] );
    unset( $fields['shipping']['shipping_last_name'] );
    unset( $fields['shipping']['shipping_address_1'] );
    unset( $fields['shipping']['shipping_address_2'] );
    unset( $fields['shipping']['shipping_city'] );
    unset( $fields['shipping']['shipping_postcode'] );

    // Order fields
    unset( $fields['order']['order_comments'] );

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'wc_remove_checkout_fields' );

