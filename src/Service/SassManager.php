<?php

namespace Drupal\styling_profiles\Service;

use Drupal\iq_barrio_helper\Service\iqBarrioService;
use Drupal\styling_profiles\Entity\StylingProfile;

/**
 * Sass Manager class.
 */
class SassManager {

  /**
   * Undocumented variable.
   *
   * @var [type]
   */
  protected $barrioService = NULL;

  /**
   * The app root.
   *
   * @var string
   */
  protected string $root;

  /**
   * Creates a new SassManager.
   *
   * @param \Drupal\iq_barrio_helper\Service\iqBarrioService $barrioService
   *   The barrio helper service.
   * @param string $root
   *   The app root.
   */
  public function __construct(iqBarrioService $barrioService, $root) {
    $this->barrioService = $barrioService;
    $this->root = $root;
  }

  /**
   * Copy the themes sass files for the given profile.
   *
   * @param \Drupal\styling_profiles\Entity\StylingProfile $profile
   *   Profile.
   *
   * @return void
   *   Return void.
   */
  public function provideSass(StylingProfile $profile) {
    $id = $profile->get('id');

    // Clone stylesheets from custom themes.
    $themes = [
      $this->root . '/themes/custom/iq_barrio',
      $this->root . '/themes/custom/iq_custom',
    ];

    foreach ($themes as $theme) {
      $themeFiles = array_keys(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($theme))));
      foreach ($themeFiles as $filename) {
        if (in_array(pathinfo($filename, PATHINFO_EXTENSION), ['scss', 'ini', 'rb'])) {
          $fileDest = str_replace('/themes/custom', '/sites/default/files/styling_profiles/' . $id, $filename);
          $path = pathinfo($fileDest);
          if (!file_exists($path['dirname'])) {
            mkdir($path['dirname'], 0755, TRUE);
          }
          copy($filename, $fileDest);
        }
      }
    }
  }

  /**
   * Write the definitions file for the profile.
   *
   * @param \Drupal\styling_profiles\Entity\StylingProfile $profile
   *   The profile to write the file for.
   */
  public function writeDefinitionsFile(StylingProfile $profile) {
    $stylingValues = $profile->get('styles');
    $pathDefinitionTarget = $this->root . '/sites/default/files/styling_profiles/' . $profile->id() . '/iq_barrio/resources/sass/_definitions.scss';
    $pathDefinitionSource = $this->root . '/themes/custom/iq_barrio/resources/sass/_template.scss.txt';

    if (!empty($stylingValues)) {
      $this->barrioService->writeDefinitionsFile(
        $stylingValues,
        $pathDefinitionTarget,
        $pathDefinitionSource
      );
    }
  }

}
