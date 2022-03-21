<?php
include 'header-thankyou.php';
include 'header-logo.php';

$thumbnail_url = get_the_post_thumbnail_url($order['product']->ID,'full');
?>
<div class="ui text container">
    <div class="thankyou">
        <h2>Halo <?php echo $order['user']->display_name; ?></h2>
        <div class="thankyou-info-1">
            <p><?php _e('Terima kasih'); ?></p>
            <p><?php printf(__('Pesanan untuk order INV %s sedang diproses', 'sejoli-bizappay'), $order['ID']); ?></p>
            <p><?php _e('Kami menunggu informasi dari bizappay untuk proses pembayaran', 'sejoli-bizappay'); ?></p>
        </div>
    </div>
</div>

<?php
include 'footer-secure.php';
include 'footer.php';
