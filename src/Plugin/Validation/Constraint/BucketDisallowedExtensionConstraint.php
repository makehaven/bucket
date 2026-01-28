<?php

declare(strict_types=1);

namespace Drupal\bucket\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Disallowed file extension constraint for Bucket uploads.
 */
#[Constraint(
  id: 'BucketDisallowedExtension',
  label: new TranslatableMarkup('Bucket Disallowed Extension', [], ['context' => 'Validation']),
  type: 'file'
)]
class BucketDisallowedExtensionConstraint extends SymfonyConstraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $message = 'The %ext extension is not allowed for security reasons.';

  /**
   * The blocked file extensions.
   *
   * @var string
   */
  public string $blocked;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'blocked';
  }

}
