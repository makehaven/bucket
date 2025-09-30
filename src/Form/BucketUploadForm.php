<?php

namespace Drupal\bucket\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Cache\Cache;

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

    $form['#attached']['library'][] = 'bucket/autoupload';

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
      'file_validate_size' => [$max_mb * 1024 * 1024],
    ];

    if ($use_blocklist) {
      if ($permissive !== '') {
        $validators['file_validate_extensions'] = [$permissive];
      }
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
      '#title' => $this->t('Select and upload files'),
      '#description' => $this->t('Your files will be uploaded automatically after you select them.'),
      '#multiple' => TRUE,
      '#upload_location' => $destination,
      '#upload_validators' => $validators,
      '#required' => TRUE,
      '#after_build' => ['::afterBuildUpload'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#attributes' => [
        'class' => ['visually-hidden'],
        'data-bucket-submit' => 'true',
      ],
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
    $element['upload_button']['#ajax'] = [
      'callback' => '::triggerAutosubmit',
    ];
    return $element;
  }

  public function triggerAutosubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand(NULL, 'bucketAutosubmit'));
    return $response;
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

    // Invalidate cache tags to ensure the /bucket/my page is updated.
    Cache::invalidateTags(['file_list', 'user:' . $uid]);

    $this->messenger()->addStatus($this->t('Uploaded @count file(s).', ['@count' => count($fids)]));
    $form_state->setRedirect('bucket.my');
  }

}