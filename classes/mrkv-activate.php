<?php 
# Check if class exist
if (!class_exists('MRKV_ACTIVATION')){
	/**
	 * Class for check all data before activation
	 */
	class MRKV_ACTIVATION
	{
		/**
		 * @var string Path to plugin
		 * */
		private $file_name;

		/**
		 * Constructor for check plugin
		 * @var string Directory plugin path
		 * */
		function __construct($file_name)
		{
			$this->file_name = $file_name;
			# Register main activation
			register_activation_hook($file_name, array($this, 'activate_mrkv_vchasno_kasa'));

			# Notice Error Function
			add_action('admin_notices', array($this, 'mrkv_kasa_admin_notice'));

			# Function for deactive plugin
			add_action( 'admin_init', array($this, 'mrkv_deactivate_kasa'));
		}

		/**
		 * Function for check all data before activation
		 * @var string Network variable
		 * */
		public function activate_mrkv_vchasno_kasa($network_wide){
		    # Add plugins file
		    if( !function_exists('is_plugin_active') ){
		        include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		    }
		    # Check if Woo is active
		    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		        set_transient( 'mrkv_deactivate_kasa', true );
		    }
		}	

		/**
		 * Notice Error Function
		 * */
		public function mrkv_kasa_admin_notice(){
		    # Get transition variable
		    $mrkv_deactivate_kasa = get_transient( 'mrkv_deactivate_kasa' );

		    # If variable set
		    if( $mrkv_deactivate_kasa ){
		        # Show admin notice
		        $this->get_error_active_message();

		        # Delete transition variable
		        delete_transient( 'mrkv_deactivate_kasa' );
		    }
		}

		/**
		 * Function for deactive plugin
		 * */
		public function mrkv_deactivate_kasa() {
		    # Get transition variable
		    $mrkv_deactivate_kasa = get_transient( 'mrkv_deactivate_kasa' );

		    # If variable set
		    if( $mrkv_deactivate_kasa ){
		        # Disable plugin
		        deactivate_plugins( plugin_basename( $this->file_name ) );
		    }

		    # Unset activate variable
		    unset($_GET['activate']);
		}

		/**
		 * Get error message of plugin activate
		 * */
		private function get_error_active_message(){
			# Mwssage Woo error
			echo '<div class="error"><p>' . esc_html( __('Потрібно активувати плагін Woocommerce, щоб встановити плагін Вчасно Каса від "Morkva', 'mrkv-vchasno-kasa') ) . '</p></div>';
		}
	}
}
?>