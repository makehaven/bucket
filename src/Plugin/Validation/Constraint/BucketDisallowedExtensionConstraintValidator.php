<?php

declare(strict_types=1);

namespace Drupal\bucket\Plugin\Validation\Constraint;

use Drupal\file\Plugin\Validation\Constraint\BaseFileConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the Bucket disallowed extension constraint.
 */
class BucketDisallowedExtensionConstraintValidator extends BaseFileConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $file = $this->assertValueIsFile($value);
    if (!$constraint instanceof BucketDisallowedExtensionConstraint) {
      throw new UnexpectedTypeException($constraint, BucketDisallowedExtensionConstraint::class);
    }

    $blocked_raw = trim((string) $constraint->blocked);
    if ($blocked_raw === '') {
      return;
    }

    $blocked = preg_split('/\s+/', $blocked_raw, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($blocked)) {
      return;
    }

    $name = $file->isTemporary() ? $file->getFilename() : $file->getFileUri();
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
    if ($ext === '') {
      return;
    }

    $blocked_lower = array_map('strtolower', $blocked);
    if (in_array($ext, $blocked_lower, TRUE)) {
      $this->context->addViolation($constraint->message, ['%ext' => $ext]);
    }
  }

}
