<?php
# Include all classes
include plugin_dir_path(__DIR__) . "classes/mrkv-logger-kasa.php"; 

# Check if class exist
if (!class_exists('MRKV_CONNECT_DATA')){
	/**
	 * Class get data to connect
	 */
	class MRKV_CONNECT_DATA
	{
		/**
		 * @var string Token number
		 * */
		private $token;

		/**
		 * @var string Payment type
		 * */
		private $type;

		/**
		 * @var string Cachier
		 * */
		private $cachier;

		/**
		 * @var string Tax Group
		 * */
		private $tax_group;

		/**
		 * Constructor for log
		 * */
		function __construct(){
			# Get token 
			$this->token = ($this->test_token_enabled()) ? get_option('mrkv_kasa_test_token') : get_option('mrkv_kasa_token');

			# Save type
			$this->type = (get_option('mrkv_kasa_code_type_payment')) ? get_option('mrkv_kasa_code_type_payment') : '0';

			# Save cachier
			$this->cachier = (get_option('mrkv_kasa_cashier', '')) ? get_option('mrkv_kasa_cashier', '') : '';

			# Save tax group
			$this->tax_group = (get_option('mrkv_kasa_tax_group', '1')) ? get_option('mrkv_kasa_tax_group', '1') : '1';
		}

		/**
		 * Return all data to connect
		 * @return array All data to connect
		 * */
		public function get_connect_data(){
			# Return connect data
			return array(
				'token' => $this->token,
				'type' => $this->type,
				'cachier' => $this->cachier,
				'tax_group' => $this->tax_group
			);
		}

		/**
		 * Check option token test
		 * @return boolean Test enabled
		 * */
		private function test_token_enabled(){
			# Get test enabled
			$test_token_active = get_option('mrkv_kasa_test_enabled');

			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Add data to lof if active
			if($test_token_active){
				# Add log data
				$log->save_log(__('Використовується тестовий токен', 'mrkv-vchasno-kasa'));
			}
			else{
				# Add log data
				$log->save_log(__('Використовується загальний токен', 'mrkv-vchasno-kasa'));
			}

			# Return enabled status
			return $test_token_active;
		}
	}
}