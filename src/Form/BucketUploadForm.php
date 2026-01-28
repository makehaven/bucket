<?php

namespace Drupal\bucket\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;

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

    $validators = [
      'FileSizeLimit' => ['fileLimit' => $max_mb * 1024 * 1024],
    ];

    if ($use_blocklist) {
      if ($permissive !== '') {
        $validators['FileExtension'] = ['extensions' => $permissive];
      }
      else {
        // Allow all extensions in blocklist mode unless restricted by the
        // disallowed list below.
        $validators['FileExtension'] = [];
      }
    }
    else {
      if ($allowed !== '') {
        $validators['FileExtension'] = ['extensions' => $allowed];
      }
      else {
        // Explicitly allow all extensions when the allowlist is blank.
        $validators['FileExtension'] = [];
      }
    }

    if ($use_blocklist && $blocked !== '') {
      $validators['BucketDisallowedExtension'] = ['blocked' => $blocked];
    }

    $form['files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select and upload files'),
      '#description' => $this->t('Files upload as soon as you select them. When ready, press "Upload queued files" to finish.'),
      '#multiple' => TRUE,
      '#upload_location' => $destination,
      '#upload_validators' => $validators,
      '#required' => TRUE,
      '#after_build' => ['::afterBuildUpload'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload queued files'),
      '#button_type' => 'primary',
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

  public static function afterBuildUpload(array $element, FormStateInterface $form_state) {
    $element['#attributes']['class'][] = 'bucket-managed-file';

    if (isset($element['upload']['#attributes']['class'])) {
      $element['upload']['#attributes']['class'][] = 'bucket-managed-file__input';
    }
    else {
      $element['upload']['#attributes']['class'] = ['bucket-managed-file__input'];
    }

    // Simplify the UI by removing manual removal controls.
    unset($element['remove_button']);

    // Reformat the uploaded file list to show read-only entries.
    foreach ($element as $key => &$child) {
      if (strpos($key, 'file_') === 0 && isset($child['selected'])) {
        $markup = $child['selected']['#title'] ?? '';
        $child['filename'] = [
          '#type' => 'markup',
          '#markup' => Markup::create('<div class="bucket-file-list__item">' . $markup . '</div>'),
        ];
        unset($child['selected']);
      }
    }

    unset($child);

    return $element;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('files') ?? [];
    $count = static::completeUpload($fids);

    $this->messenger()->addStatus($this->formatPlural($count, 'Uploaded 1 file.', 'Uploaded @count files.', ['@count' => $count]));
    $form_state->setRedirect('bucket.my');
  }

  /**
   * Finalizes uploaded files by persisting records and marking them permanent.
   */
  protected static function completeUpload(array $fids): int {
    if (empty($fids)) {
      return 0;
    }

    $uid = (int) \Drupal::currentUser()->id();
    $time = \Drupal::time()->getRequestTime();
    $db = \Drupal::database();
    $saved = 0;

    $existing = $db->select('bucket_item', 'bi')
      ->fields('bi', ['fid'])
      ->condition('fid', $fids, 'IN')
      ->execute()
      ->fetchCol();
    $existing = array_map('intval', $existing);

    $pending = array_diff($fids, $existing);

    if (empty($pending)) {
      return 0;
    }

    foreach ($pending as $fid) {
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

        $saved++;
      }
    }

    // Invalidate cache tags to ensure the /bucket/my page is updated.
    if ($saved > 0) {
      Cache::invalidateTags(['file_list', 'user:' . $uid]);
    }

    return $saved;
  }

}
