<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2019-01-30 20:58
 * @File name           : _member.php
 */
?>

<?php if ($is_login) : ?>

<section id="section1 container-fluid">
    <header class="c-header">
        <div class="mask"></div>
        <?php include '_navbar.php'; ?>
    </header>
</section>

<div class="container py-4">
    <?php echo $main_content; ?>

    <?php if (function_exists('rec_render_widget')) echo rec_render_widget(); ?>
</div>

<?php else: ?>

    <div class="result-search page-member-area">
        <section id="section1 container-fluid">
            <header class="c-header">
                <div class="mask"></div>
                <?php include '_navbar.php'; ?>
            </header>
            <?php include '_search-form.php'; ?>
        </section>

        <div class="container py-4">
            <div class="row">
                <div class="col-md-8">
                    <?php echo $main_content; ?>
                </div>
                <div class="col-md-4"></div>
            </div>
        </div>
    </div>

<?php endif; ?>
