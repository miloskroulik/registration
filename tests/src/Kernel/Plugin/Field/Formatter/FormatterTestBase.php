<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base test for kernel formatter tests.
 */
abstract class FormatterTestBase extends RegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    $formats = $this->entityTypeManager
      ->getStorage('date_format')
      ->loadMultiple(['long', 'medium', 'short']);
    $formats['long']->setPattern('l, j. F Y - G:i')->save();
    $formats['medium']->setPattern('j. F Y - G:i')->save();
    $formats['short']->setPattern('Y M j - g:ia')->save();
  }

  /**
   * Render a field.
   *
   * @param array $build
   *   The render array for the field.
   *
   * @return string
   *   The rendered output, with <div> tags removed to make assertions easier.
   */
  protected function renderPlain(array $build): string {

    if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
      $output = trim($this->container->get('renderer')->renderInIsolation($build));
    }
    else {
      // @phpstan-ignore-next-line
      $output = trim($this->container->get('renderer')->renderPlain($build));
    }

    return preg_replace("/<div>(.*?)<\/div>/", "$1", $output);
  }

}
