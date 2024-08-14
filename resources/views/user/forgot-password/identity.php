<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Forgot Password') ?></title>
        <meta name="description" content="<?= $view->etrans('Forgot Password') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Forgot Password') ?></h1>
            
            <div class="form-fields p-m mt-s">
            
                <?php $form = $view->form(); ?>

                <?= $form->form(['action' => $view->routeUrl('forgot-password.identity.verify')]) ?>
                
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Name'),
                            for: 'address.name',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'address.name',
                            attributes: ['required', 'autofocus', 'autocomplete' => 'name'],
                        ) ?>
                        <p class="text-xxs"><?= $view->etrans('The name you have registered.') ?></p>
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('User'),
                            for: 'user',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'user',
                            attributes: ['required', 'autofocus', 'autocomplete' => 'username'],
                        ) ?>
                        <p class="text-xxs"><?= $view->etrans('E-Mail or Smartphone.') ?></p>
                    </div>
                </div>

                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Send password reset link'), attributes: ['class' => 'button primary text-xs']) ?>
                </div>

                <?= $form->close() ?>
                
            </div>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>