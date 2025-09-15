<?php

namespace Drupal\bucket\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BucketSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['bucket.settings'];
  }

  public function getFormId() {
    return 'bucket_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $cfg = $this->config('bucket.settings');

    $form['ttl_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Time-to-live (hours)'),
      '#default_value' => $cfg->get('ttl_hours'),
      '#min' => 1,
      '#description' => $this->t('Files older than this will be deleted by cron.'),
    ];

    $form['delete_on_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete file immediately after download'),
      '#default_value' => $cfg->get('delete_on_download'),
      '#description' => $this->t('If enabled, files are deleted by cron after first download.'),
    ];

    $form['use_blocklist'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use blocklist (disallow list) instead of allowlist'),
      '#default_value' => $cfg->get('use_blocklist'),
    ];

    $form['allowed_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions (allowlist)'),
      '#default_value' => $cfg->get('allowed_extensions'),
      '#description' => $this->t('Space-separated. Leave blank to allow all.'),
      '#states' => [
        'visible' => [
          ':input[name="use_blocklist"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['blocked_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Blocked file extensions (disallow list)'),
      '#default_value' => $cfg->get('blocked_extensions'),
      '#description' => $this->t('Space-separated, e.g., "php js exe sh bat".'),
      '#states' => [
        'visible' => [
          ':input[name="use_blocklist"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['permissive_extensions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Permissive extensions used in blocklist mode'),
      '#default_value' => $cfg->get('permissive_extensions'),
      '#description' => $this->t("In blocklist mode, Drupal's default file validation is too restrictive. To work around this, we must provide a large list of 'allowed' extensions here to override the default, and then this module can apply the smaller blocklist from the setting above. It's recommended to keep this populated with the default list of common, safe file types."),
      '#rows' => 5,
      '#states' => [
        'visible' => [
          ':input[name="use_blocklist"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['max_filesize_mb'] = [
      '#type' => 'number',
      '#title' => $this->t('Max file size (MB)'),
      '#default_value' => $cfg->get('max_filesize_mb'),
      '#min' => 1,
    ];

    $form['list_page_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('List page limit'),
      '#default_value' => $cfg->get('list_page_limit'),
      '#min' => 10,
      '#max' => 2000,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $cfg->get('description'),
      '#description' => $this->t('Tokens: [ttl_hours], [delete_on_download]. Shown on /bucket and /bucket/upload.'),
      '#rows' => 5,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('bucket.settings')
      ->set('ttl_hours', (int) $form_state->getValue('ttl_hours'))
      ->set('delete_on_download', (bool) $form_state->getValue('delete_on_download'))
      ->set('use_blocklist', (bool) $form_state->getValue('use_blocklist'))
      ->set('allowed_extensions', trim($form_state->getValue('allowed_extensions')))
      ->set('blocked_extensions', trim($form_state->getValue('blocked_extensions')))
      ->set('permissive_extensions', trim($form_state->getValue('permissive_extensions')))
      ->set('max_filesize_mb', (int) $form_state->getValue('max_filesize_mb'))
      ->set('list_page_limit', (int) $form_state->getValue('list_page_limit'))
      ->set('description', $form_state->getValue('description'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
