<?php

namespace Drupal\bucket\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;

class BucketUploadForm extends FormBase {

  public function getFormId() {
    return 'bucket_upload_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $cfg = $this->config('bucket.settings');
    $uid = $this->currentUser()->id();
    $destination = "public://bucket/$uid";

    \Drupal::service('file_system')->prepareDirectory(
      $destination,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    // Description
    $desc = (string) $cfg->get('description');
    $desc = strtr($desc, [
      '[ttl_hours]' => (string) ($cfg->get('ttl_hours') ?? 48),
      '[delete_on_download]' => ($cfg->get('delete_on_download') ? 'Immediate' : 'No'),
    ]);
    $form['bucket_description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="bucket-desc">' . nl2br($desc) . '</div>',
    ];

    $max_mb = (int) $cfg->get('max_filesize_mb') ?: 20;
    $use_blocklist = (bool) $cfg->get('use_blocklist');
    $allowed = trim((string) $cfg->get('allowed_extensions'));
    $blocked = trim((string) $cfg->get('blocked_extensions'));
    $permissive = trim((string) $cfg->get('permissive_extensions'));

    // NOTE: In blocklist mode, we must apply a broad file_validate_extensions in
    // addition to our custom validator. Some stacks apply a hidden allowlist
    // causing svg/zip to fail; our custom blocklist validator is not enough.
    $validators = [
      'file_validate_size' => [$max_mb * 1024 * 1024],
    ];

    if ($use_blocklist) {
      // First, set a permissive filter, since some Drupal stacks have a default
      // allowlist that is too restrictive for our use-case.
      if ($permissive !== '') {
        $validators['file_validate_extensions'] = [$permissive];
      }
      // Next, apply our custom blocklist validator.
      if ($blocked !== '') {
        $validators['bucket_validate_disallowed_extensions'] = [$blocked];
      }
    }
    else {
      if ($allowed !== '') {
        $validators['file_validate_extensions'] = [$allowed];
      }
    }

    $form['files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select files'),
      '#multiple' => TRUE,
      '#upload_location' => $destination,
      '#upload_validators' => $validators,
      '#required' => TRUE,
      '#description' => $use_blocklist
        ? $this->t('Max @mb MB each. Blocked: @ext', ['@mb' => $max_mb, '@ext' => ($blocked ?: $this->t('(none)'))])
        : $this->t('Max @mb MB each. Allowed: @ext', ['@mb' => $max_mb, '@ext' => ($allowed ?: $this->t('(all)'))]),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];

    $form['links'] = [
      '#type' => 'container',
      'recent' => [
        '#type' => 'link',
        '#title' => $this->t('Recent uploads'),
        '#url' => Url::fromRoute('bucket.list'),
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ],
      'my' => [
        '#type' => 'link',
        '#title' => $this->t('My files'),
        '#url' => Url::fromRoute('bucket.my'),
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ],
      '#attributes' => ['class' => ['bucket-links']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('files') ?? [];
    $uid = (int) $this->currentUser()->id();
    $time = \Drupal::time()->getRequestTime();
    $db = \Drupal::database();

    foreach ($fids as $fid) {
      if ($file = File::load($fid)) {
        $file->setPermanent();
        $file->save();

        $db->insert('bucket_item')
          ->fields([
            'fid' => (int) $fid,
            'uid' => $uid,
            'created' => $time,
            'downloaded' => 0,
            'downloaded_at' => NULL,
          ])->execute();
      }
    }

    $this->messenger()->addStatus($this->t('Uploaded @count file(s).', ['@count' => count($fids)]));
    $form_state->setRedirect('bucket.my');
  }

}
