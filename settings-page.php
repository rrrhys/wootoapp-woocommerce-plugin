<?php

$settings = [];
$settings['store_id']   = get_option( 'WC_settings_wootoapp_site_id' );
$settings['secret_key'] = get_option( 'WC_settings_wootoapp_secret_key' );

$force_use_local = false;
$force_use_local_db = false;
$dev_url = "www.wooc.local";
$use_local_react = $_SERVER['HTTP_HOST'] === $dev_url || $force_use_local;//true;
$use_prod_db =  ($force_use_local_db || ( $_SERVER['HTTP_HOST'] === $dev_url)) ? false : true;

$using_some_dev_params = !!($force_use_local || $force_use_local_db);
$args               = array(
	'taxonomy'   => "product_cat",
	'number'     => $number,
	'orderby'    => $orderby,
	'order'      => $order,
	'hide_empty' => $hide_empty,
	'include'    => $ids
);
$product_categories = get_terms( $args );


$paypal_email = "";

$paypal_opts = get_option("woocommerce_paypal_settings");
if($paypal_opts){
    $paypal_email = $paypal_opts['email'];
}

?>

<?php if( $using_some_dev_params): ?>
    <h1>WARNING - DEV MODE</h1>
<?php endif; ?>
<link rel="stylesheet" href="https://app.wootoapp.com/wta-wc-react.css">
<div id="wta-root">

</div>
<script type="text/javascript">
    window.WooToApp = {
        auth: {
            id: '<?php echo str_replace("\'", "", $settings['store_id']); ?>',
            secret_key: '<?php echo str_replace( "\'", "", $settings['secret_key']); ?>'
        },
        environment: "<?php echo $use_prod_db ? "prod" : "dev"; ?>",
        has_dev_params: <?php echo $using_some_dev_params ? "true" : "false";?>,
        categories: <?php echo json_encode($product_categories); ?>,
        pages: <?php echo json_encode(get_pages()); ?>,
        woo_currencies: <?php echo json_encode(get_woocommerce_currencies()); ?>,
        currency: "<?php echo get_woocommerce_currency(); ?>",
        paypal_email: "<?php echo $paypal_email; ?>"
    }

    window.WooToApp.log = window.WooToApp.environment == "prod" ? function(){} : console.log;

    console.log(window.WooToApp);

</script>



<?php if( $use_local_react): ?>
    <script type="text/javascript" src="http://localhost:3000/static/js/bundle.js"></script>

<?php else: ?>
    <script type="text/javascript" src="https://app.wootoapp.com/wta-wc-react.js"></script>

<?php endif; ?>

