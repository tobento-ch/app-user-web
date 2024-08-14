<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Account Verification') ?></title>
        <meta name="description" content="<?= $view->etrans('Account Verification') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Account Verification') ?></h1>
            
            <p class="text-body"><?= $view->etrans('Thanks for signing up! Before getting started, could you verify at least one channel to activate your account?') ?></p>
            
            <div class="mt-m">
                <?= $view->render('user/verification/channels') ?>
            </div>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>