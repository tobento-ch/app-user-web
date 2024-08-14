<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Channel Verification') ?></title>
        <meta name="description" content="<?= $view->etrans('Two-Factor Authentication') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl mb-m"><?= $view->etrans('Two-Factor Authentication') ?></h1>
            
            <?php $form = $view->form(); ?>
            <?= $form->form(['action' => $view->routeUrl('twofactor.code.verify')]) ?>
            <div class="buttons grouped">
                <?= $form->input(
                    name: 'code',
                    attributes: [
                        'autocomplete' => 'one-time-code',
                        'required',
                        'autofocus',
                        'inputmode' => 'numeric',
                        'pattern' => '[0-9]*',
                    ],
                ) ?>
                <?= $form->button(text: $view->trans('Confirm Code'), attributes: ['class' => 'button primary']) ?>
            </div>
            <?= $form->close() ?>
            
            <p class="text-body mt-m mb-s"><?= $view->etrans('If you did not receive the verification code, we will gladly send you another.') ?></p>
            
            <?php $form = $view->form(); ?>
            <?= $form->form(['action' => $view->routeUrl('twofactor.code.resend')]) ?>
            <?= $form->button(text: $view->trans('Resend Verification Code'), attributes: ['class' => 'button']) ?>
            <?= $form->close() ?>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>