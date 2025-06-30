<?php
# Include shift object
include plugin_dir_path(__DIR__) . "classes/mrkv-shift.php"; 

# Get current Shift data
$shift = new MRKV_SHIFT();

# Check post value
if ( isset($_POST['new_status']) && isset($_POST['mrkv_shift_status_nonce']) 
     && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mrkv_shift_status_nonce']), 'mrkv_shift_status_action'))) {

    $new_status = sanitize_text_field(wp_unslash($_POST['new_status']));

    ($new_status == 0) ? $shift->close_shift() : $shift->open_shift();
}


# Update status shift
$shift->update_shift_status();

# Get Shift status after update
$is_connected = get_option('mrkv_kasa_shift_status', '0');
# Get Shift status name
$status = $shift->get_current_shift_status_name();

# Log file open
$debug_log = file_get_contents(__DIR__ . '/../logs/debug.log');
?>
<style>
    .mrkv-widget-template__form button{background: #EAB5F7 !important;background-color: #EAB5F7 !important;border-color: #cec2d1 !important;color: #010101 !important;font-weight: 600;font-size: 15px;padding: 0 27px;}
    .mrkv-widget-template__form button:hover{opacity:70%;}
    #status_widget_vchasno h2{display: flex;justify-content: flex-start;}
    #status_widget_vchasno h2 img{margin-left: 10px;}
    .mrkv-widget-template pre{margin-top: 20px;border: 1px solid #dcdcde;padding: 20px;background: #fbfbfb;height: 300px;overflow-y: scroll;}
    .mrkv-widget-template h3{margin-top: 20px !important;font-weight:600 !important;}
</style>
<div class="mrkv-widget-template">
    <form class="mrkv-widget-template__form" method="post">
        <p><?php esc_html_e('Поточний статус зміни', 'mrkv-vchasno-kasa'); ?>: <span id="mrkv_kasa_shift_status" class="status" style="font-weight: 500; text-transform: uppercase;"><?php echo esc_html($status); ?></span></p>
        <?php 
        if($is_connected){
            ?>
                <div class="ppo_disconnect-group" style="<?php echo esc_html(( ! $is_connected ) ? 'display: none;' : 'display: inline-flex;'); ?> align-items: center;">
                    <button type="submit" id="ppo_button_disconnect" class="end button button-secondary"><?php esc_html_e('Закрити зміну', 'mrkv-vchasno-kasa'); ?></button>
                </div>
                <input type="hidden" name="new_status" value="0">
            <?php
        }
        else{
            ?>
            <div class="ppo_connect-group" style="<?php echo esc_html(( $is_connected ) ? 'display: none;' : 'display: inline-flex;'); ?> align-items: center;¨" >
                <button type="submit" id="ppo_button_connect" class="start button button-secondary"><?php esc_html_e('Відкрити зміну', 'mrkv-vchasno-kasa'); ?></button>
            </div>
            <input type="hidden" name="new_status" value="1">
            <?php
        }
        ?>
        <?php wp_nonce_field( 'mrkv_shift_status_action', 'mrkv_shift_status_nonce' ); ?>
    </form>
    <h3><?php esc_html_e('Лог:', 'mrkv-vchasno-kasa'); ?></h3>
    <hr>
    <pre><?php echo esc_html( $debug_log ); ?></pre>
</div>