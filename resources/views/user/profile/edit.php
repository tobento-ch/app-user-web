<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Profile') ?></title>
        <meta name="description" content="<?= $view->etrans('Profile') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        
        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Profile') ?></h1>
            
            <section class="form-fields p-m mt-s">
                <h2 class="text-m mb-m"><?= $view->etrans('Profile Information') ?></h2>

                <?php $form = $view->form(); ?>
                <?= $form->form([
                    'action' => $view->routeUrl('profile.update'),
                    'method' => 'PATCH',
                    'name' => 'update',
                ]) ?>

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
                            value: $user->address()->name(),
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
                            value: $user->email(),
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
                            value: $user->smartphone(),
                            attributes: ['minlength' => '8', 'autocomplete' => 'tel'],
                        ) ?>
                    </div>
                </div>

                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Save'), attributes: ['class' => 'button primary text-xs']) ?>
                </div>

                <?= $form->close() ?>
            </section>
            
            <?php if ($channels->count() > 0) { ?>
            <section class="form-fields p-m mt-m">
                <h2 class="text-m mb-m"><?= $view->etrans('Channel Verifications') ?></h2>
                <?= $view->render('user/verification/channels') ?>
            </section>
            <?php } ?>
            
            <section class="form-fields p-m mt-m">
                <h2 class="text-m mb-m"><?= $view->etrans('Update Password') ?></h2>

                <?php $form = $view->form(); ?>
                <?= $form->form([
                    'action' => $view->routeUrl('profile.password.update'),
                    'method' => 'PATCH',
                    'name' => 'password_update',
                ]) ?>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Current Password'),
                            for: 'current_password',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'current_password',
                            type: 'password',
                            attributes: ['required', 'minlength' => '8', 'autocomplete' => 'current-password'],
                            withInput: false
                        ) ?>
                    </div>
                </div>
                
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('New Password'),
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
                
                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Save'), attributes: ['class' => 'button primary text-xs']) ?>
                </div>

                <?= $form->close() ?>
            </section>
            
            <section class="form-fields p-m mt-m">
                <h2 class="text-m"><?= $view->etrans('Delete Account') ?></h2>
                <p class="text-body mb-m"><?= $view->etrans('Once your account is deleted, all of its resources and data will be permanently deleted.') ?></p>
                
                <?php $form = $view->form(); ?>
                <?= $form->form([
                    'action' => $view->routeUrl('profile.delete'),
                    'method' => 'DELETE',
                    'name' => 'delete',
                ]) ?>
                
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Current Password'),
                            for: 'delete_password',
                            requiredText: $view->trans('required'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->input(
                            name: 'delete_password',
                            type: 'password',
                            attributes: ['required', 'minlength' => '8', 'id' => 'delete_password'],
                            withInput: false
                        ) ?>
                    </div>
                </div>
                
                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Delete Account'), attributes: ['class' => 'button text-xs']) ?>
                </div>

                <?= $form->close() ?>
            </section>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>