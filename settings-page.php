<?php

$settings = [];
$settings['store_id']   = get_option( 'WC_settings_wootoapp_site_id' );
$settings['secret_key'] = get_option( 'WC_settings_wootoapp_secret_key' );

$dev_url = "www.wooc.local";

$args               = array(
	'taxonomy'   => "product_cat",
	'number'     => $number,
	'orderby'    => $orderby,
	'order'      => $order,
	'hide_empty' => $hide_empty,
	'include'    => $ids
);
$product_categories = get_terms( $args );


?>
<div id="wta-root">

</div>
<script type="text/javascript">
    window.WooToApp = {
        auth: {
            id: '<?php echo str_replace("\'", "", $settings['store_id']); ?>',
            secret_key: '<?php echo str_replace( "\'", "", $settings['secret_key']); ?>'
        },
        environment: "<?php echo $_SERVER['HTTP_HOST'] === $dev_url ? "dev" : "prod"; ?>",
        categories: <?php echo json_encode($product_categories); ?>
    }

    window.WooToApp.log = window.WooToApp.environment == "prod" ? function(){} : console.log;

    console.log(window.WooToApp);

</script>



<?php if( $_SERVER['HTTP_HOST'] === $dev_url): ?>
    <script type="text/javascript" src="http://localhost:3000/static/js/bundle.js"></script>

<?php else: ?>
    <script type="text/javascript" src="https://app.wootoapp.com/wta-wc-react.js"></script>

<?php endif; ?>

