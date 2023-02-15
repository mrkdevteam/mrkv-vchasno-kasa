<?php
# Check if class exist
if (!class_exists('MRKV_LOGGER_KASA')){
	/**
	 * Class for save log
	 */
	class MRKV_LOGGER_KASA
	{
		/**
		 * @var string Directory of log file
		 * */
		private $file_path_log;

		/**
		 * @var string File name
		 * */
		const LOG_FILE_NAME = "logs/debug.log";

		/**
		 * Constructor for log
		 * */
		function __construct(){
			# Save path
			$this->file_path_log =  plugin_dir_path(__DIR__) . self::LOG_FILE_NAME; 
		}

		/**
		 * Save log data in file
		 * @var string Message save
		 * */
		public function save_log($text){
			# Get current datetime
			$date_now = date_i18n("Y-m-d h:i:sa");

			# Add line break 
			$text_debug = $date_now . ': ' . $text . " \r\n";
			# Save text
			file_put_contents( $this->file_path_log, $text_debug, FILE_APPEND );
		}

		/**
		 * Clear log
		 * */
		public function clear_file_log(){
			file_put_contents( $this->file_path_log, '');
		}
	}
}