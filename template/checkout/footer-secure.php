    <div class="footer-secure">
        <p>
            <span class="secure-tagline-img"><img src="<?php echo SEJOLISA_URL; ?>public/img/shield.png"> Informasi Pribadi Anda Aman</span>
            <?php if(false !== boolval(carbon_get_the_post_meta('display_warranty_label'))) : ?>
            <span class="secure-tagline-img"><img src="<?php echo SEJOLISA_URL; ?>public/img/guarantee.png"> 100% Garansi Uang Kembali</span>
            <?php endif; ?>
        </p>
    </div>
