<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Login') ?></title>
        <meta name="description" content="<?= $view->etrans('Login') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Login') ?></h1>
            
            <div class="form-fields p-m mt-s">
                
                <?php $form = $view->form(); ?>

                <?= $form->form(['action' => $view->routeUrl('login.store')]) ?>

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
                    </div>
                </div>
                
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Password'),
                            for: 'password',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'password',
                            type: 'password',
                            attributes: ['required', 'autocomplete' => 'current-password'],
                            withInput: false
                        ) ?>
                    </div>
                </div>
                
                <?php if ($remember) { ?>
                <div class="field">
                    <div class="field-body">
                        <span class="wrap-v">
                            <?= $form->input(
                                name: 'remember',
                                type: 'checkbox',
                                value: true,
                                selected: [],
                            ) ?>
                            <?= $form->label(
                                text: $view->trans('Remember me'),
                                for: 'remember',
                            ) ?>
                        </span>
                        <p class="text-xxs"><?= $view->etrans('Do not select this feature if you are using a public computer or sharing it with multiple people or make sure you log out after.') ?></p>
                    </div>
                </div>
                <?php } ?>
                
                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Log in'), attributes: ['class' => 'button primary']) ?>
                    
                    <?php if ($forgotPasswordRoute) { ?>
                        <a class="button raw" href="<?= $view->routeUrl($forgotPasswordRoute) ?>"><?= $view->etrans('Forgot your password?') ?></a>
                    <?php } ?>
                </div>
                
                <?= $form->close() ?>
            </div>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>