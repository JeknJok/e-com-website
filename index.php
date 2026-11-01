<?php
require_once __DIR__ . '/app/bootstrap.php';
$msg_customer = $msg_admin = '';
$loginEmail = '';
$loginKind = 'customer';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $loginKind = isset($_POST['submit_admin']) ? 'admin' : 'customer';
    $loginEmail = input('email');
    if (login_account($loginKind, $loginEmail, input('pass'))) {
        redirect(url($loginKind === 'admin' ? 'admin/dashboard.php' : 'admin/catalog.php'));
    }
    $message = '<div class="alert alert-danger" role="alert">Invalid email or password.</div>';
    if ($loginKind === 'admin') { $msg_admin = $message; } else { $msg_customer = $message; }
}
$admin = current_admin();
$customer = $admin ? null : current_customer();
$shopHref = $customer ? url('admin/catalog.php') : url('index.php') . '#login';
$products = rows('SELECT pcode, pname, pcategory, pprice, pimage, pdesc FROM Products WHERE is_active = 1 ORDER BY pcode LIMIT 6');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="M-A-E-Commerce: collectible finds and the most affordable items known to mankind. A personal ecommerce project by Nealle Benedict Adisaputro.">
    <title>M-A-E-Commerce · Most Known to Mankind!</title>
    <?php theme_head(); ?>
    <link rel="stylesheet" href="<?= e(url('css/landing.css')) ?>">
    <script defer src="<?= e(url('js/scripts.js')) ?>"></script>
</head>
<body class="mae-landing" id="page-top">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand mae-brand" href="#page-top"><img src="<?= e(url('assets/img/logo-mae.png')) ?>" width="44" height="42" alt=""><span>M-A-E-Commerce</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav text-uppercase ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Highlights</a></li>
                    <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($admin ? url('admin/dashboard.php') : ($customer ? url('admin/catalog.php') : '#login')) ?>"><?= $admin ? 'Dashboard' : ($customer ? 'Shop' : 'Login') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <main id="main-content" tabindex="-1">
        <header class="masthead">
            <div class="container">
                <p class="masthead-subheading">Welcome To M-A-E-Commerce!</p>
                <h1 class="masthead-heading text-uppercase">Most Known to Mankind!</h1>
                <a class="btn btn-primary btn-xl text-uppercase" href="#portfolio">Tell Me More, Mankind!</a>
            </div>
        </header>
        <section class="page-section bg-light" id="portfolio" aria-labelledby="highlights-heading">
            <div class="container">
                <div class="text-center section-intro">
                    <h2 class="section-heading text-uppercase" id="highlights-heading">Catalog Highlights</h2>
                    <p class="section-subheading">Featuring the most affordable items known to mankind!</p>
                </div>
                <div class="landing-currency"><?php currency_selector(); ?></div>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                    <div class="col-lg-4 col-sm-6">
                        <article class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#product-preview-<?= e($product['pcode']) ?>" aria-label="Preview <?= e($product['pname']) ?>">
                                <img class="img-fluid" src="<?= e(image_url($product['pimage'])) ?>" data-fallback="<?= e(url('assets/img/logo-mae.png')) ?>" width="600" height="450" loading="lazy" alt="<?= e($product['pname']) ?>">
                                <span class="portfolio-hover" aria-hidden="true"><span>View item</span></span>
                            </a>
                            <div class="portfolio-caption">
                                <h3 class="portfolio-caption-heading"><?= e($product['pname']) ?></h3>
                                <p class="portfolio-caption-subheading"><?= e($product['pcategory']) ?></p>
                                <p class="highlight-price"><?= price_html($product['pprice']) ?></p>
                            </div>
                        </article>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php if (!$products): ?><p class="text-center">New finds are on their way. Check back for our next catalog highlights.</p><?php endif ?>
                <div class="text-center mt-5"><a class="btn btn-primary" href="<?= e($shopHref) ?>"><?= $customer ? 'Explore the catalog' : 'Log in to explore the catalog' ?></a></div>
            </div>
        </section>
        <section class="page-section" id="team" aria-labelledby="team-heading">
            <div class="container text-center">
                <h2 class="section-heading text-uppercase" id="team-heading">The Team</h2>
                <p class="section-subheading">The builder of the most affordable ecommerce platform known to mankind.</p>
                <div class="team-member">
                    <img class="team-mark" src="<?= e(url('assets/img/logo-mae.png')) ?>" width="160" height="152" loading="lazy" alt="M-A-E logo">
                    <h3>Nealle Benedict Adisaputro</h3>
                    <p class="text-muted">Full Stack Web Developer</p>
                    <a class="btn btn-outline-dark" href="https://www.linkedin.com/in/nealle-benedict-adisaputro-a549681b7/">LinkedIn</a>
                </div>
                <p class="team-note">A personal project, built with a little personality.</p>
            </div>
        </section>
        <section class="page-section bg-light" id="login" aria-labelledby="login-heading">
            <div class="container">
                <div class="text-center section-intro">
                    <h2 class="section-heading text-uppercase" id="login-heading">Login</h2>
                    <p class="section-subheading">Log in or <a href="<?= e(url('admin/Customers_insert.php')) ?>">register now</a> and start shopping.</p>
                </div>
                <?php if ($customer || $admin): ?>
                <div class="signed-in-note"><p>You are already signed in.</p><a class="btn btn-primary" href="<?= e(url($admin ? 'admin/dashboard.php' : 'admin/catalog.php')) ?>"><?= $admin ? 'Go to dashboard' : 'Continue shopping' ?></a></div>
                <?php endif ?>
                <div class="login-layout">
                    <div class="login-panel">
                        <h3>Customer login</h3>
                        <p class="text-muted">Your next find is waiting.</p>
                        <form action="<?= e(url('index.php')) ?>#login" method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3"><label class="form-label" for="customer-email">Email</label><input type="email" class="form-control" id="customer-email" name="email" value="<?= e($loginKind === 'customer' ? $loginEmail : '') ?>" autocomplete="username" maxlength="254" required></div>
                            <div class="mb-4"><label class="form-label" for="customer-password">Password</label><input type="password" class="form-control" id="customer-password" name="pass" autocomplete="current-password" maxlength="72" required></div>
                            <?= $msg_customer ?>
                            <button type="submit" name="submit_customer" class="btn btn-primary">Log in</button>
                        </form>
                        <p class="register-link">New to MAE? <a href="<?= e(url('admin/Customers_insert.php')) ?>">Create an account</a></p>
                    </div>
                    <div class="login-panel admin-login">
                        <h3>You're an admin?</h3>
                        <p class="text-muted">Log in to manage products, customers, and orders.</p>
                        <form action="<?= e(url('index.php')) ?>#login" method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3"><label class="form-label" for="admin-email">Email</label><input type="email" class="form-control" id="admin-email" name="email" value="<?= e($loginKind === 'admin' ? $loginEmail : '') ?>" autocomplete="username" maxlength="254" required></div>
                            <div class="mb-4"><label class="form-label" for="admin-password">Password</label><input type="password" class="form-control" id="admin-password" name="pass" autocomplete="current-password" maxlength="72" required></div>
                            <?= $msg_admin ?>
                            <button type="submit" name="submit_admin" class="btn btn-secondary">Admin login</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer class="footer" id="contact">
        <div class="container footer-content">
            <div><strong>M-A-E-Commerce</strong><p>Copyright &copy; MAE Ecommerce <?= e(date('Y')) ?></p></div>
            <div><span>Get in touch with the builder</span><a href="https://www.linkedin.com/in/nealle-benedict-adisaputro-a549681b7/">Nealle on LinkedIn</a></div>
        </div>
    </footer>
    <?php foreach ($products as $product): ?>
    <div class="portfolio-modal modal fade" id="product-preview-<?= e($product['pcode']) ?>" tabindex="-1" aria-labelledby="product-heading-<?= e($product['pcode']) ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close preview"></button>
            <div class="modal-body">
                <p class="product-category"><?= e($product['pcategory']) ?></p>
                <h2 id="product-heading-<?= e($product['pcode']) ?>"><?= e($product['pname']) ?></h2>
                <img class="preview-image" src="<?= e(image_url($product['pimage'])) ?>" data-fallback="<?= e(url('assets/img/logo-mae.png')) ?>" width="600" height="450" loading="lazy" alt="<?= e($product['pname']) ?>">
                <p class="preview-description"><?= nl2br(e($product['pdesc'])) ?></p>
                <p class="preview-price"><?= price_html($product['pprice']) ?></p>
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close preview</button>
            </div>
        </div></div>
    </div>
    <?php endforeach ?>
</body>
</html>
