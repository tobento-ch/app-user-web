<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Register') ?></title>
        <meta name="description" content="<?= $view->etrans('Register') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Register') ?></h1>
            
            <div class="form-fields p-m mt-s">
            
                <?php $form = $view->form(); ?>

                <?= $form->form(['action' => $view->routeUrl('register.store')]) ?>
                <?= $view->spamDetector('register')->render($view) ?>
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
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('E-Mail'),
                            for: 'email',
                            requiredText: $view->trans('required without smartphone'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'email',
                            type: 'email',
                            attributes: ['autocomplete' => 'email'],
                        ) ?>
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Smartphone'),
                            for: 'smartphone',
                            requiredText: $view->trans('required without e-mail'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'smartphone',
                            attributes: ['minlength' => '8', 'autocomplete' => 'tel'],
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
                            attributes: ['required', 'minlength' => '8', 'autocomplete' => 'new-password'],
                            withInput: false
                        ) ?>
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Confirm password'),
                            for: 'password_confirmation',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'password_confirmation',
                            type: 'password',
                            attributes: ['required', 'minlength' => '8', 'autocomplete' => 'new-password'],
                            withInput: false
                        ) ?>
                    </div>
                </div>
                
                <?php if ($newsletter) { ?>
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Newsletter'),
                            for: 'newsletter',
                            optionalText: $view->trans('optional'),
                        ) ?>
                    </div>
                    <div class="field-body">                
                        <div class="cols">
                            <div>
                                <?= $form->input(
                                    name: 'newsletter',
                                    type: 'checkbox',
                                    value: '1',
                                ) ?>
                            </div>
                            <div class="col ml-xs">
                                <label for="newsletter" class="input-value"><?= $view->etrans('Yes, I would like to subscribe to the newsletter.') ?></label>
                            </div>
                        </div>           
                    </div>
                </div>
                <?php } ?>
                
                <?php if ($terms) { ?>
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Terms and conditions'),
                            for: 'terms',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <div class="cols">
                            <div>
                                <?= $form->input(
                                    name: 'terms',
                                    type: 'checkbox',
                                    value: '1',
                                    attributes: ['required'],
                                ) ?>
                            </div>
                            <div class="col ml-xs">
                                <label for="terms" class="input-value"><?= $view->etrans('I agree to the terms.') ?></label>
                                <a target="_blank" href="<?= $view->esc($termsUrl) ?>"><?= $view->etrans('Read our terms and conditions.') ?></a>
                            </div>
                        </div>           
                    </div>
                </div>
                <?php } ?>

                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Register'), attributes: ['class' => 'button primary text-xs']) ?>
                    <a href="<?= $view->routeUrl('login') ?>" class="button raw text-xs"><?= $view->etrans('Already registered?') ?></a>
                </div>

                <?= $form->close() ?>
                
            </div>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>