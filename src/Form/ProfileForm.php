<?php

namespace Drupal\styling_profiles\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the styling profile settings form.
 */
class ProfileForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $profile = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => 'Label',
      '#default_value' => $profile->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $profile->id(),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'profileExists'],
        'replace_pattern' => '[^a-z0-9_.]+',
      ],
    ];

    $form_state->setValue('styles', $profile->get('styles') );

    // load iq_barrio settings form
    // feels really wrong, surely go to hell for this one...

    require_once DRUPAL_ROOT . '/' . drupal_get_path('theme', 'iq_barrio') . "/iq_barrio.theme";
    iq_barrio_form_system_theme_settings_alter($form, $form_state);
    unset($form['#submit']);    

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $profile = $this->entity;

    // Prevent leading and trailing spaces.
    $profile->set('label', trim($form_state->getValue('label')));
    $profile->set('id', $form_state->getValue('id'));


    $styles = $form_state->getValues();
    unset( $styles['id'] );
    unset( $styles['label'] );

    $profile->set('styles', $styles);

    $status = $profile->save();
    
    $edit_link = $this->entity->link($this->t('Edit'));
    $action = $status == SAVED_UPDATED ? 'updated' : 'added';

    // clone stylesheets from custom themes
    $themes = [
      $_SERVER["DOCUMENT_ROOT"].'/themes/custom/iq_barrio',
      $_SERVER["DOCUMENT_ROOT"].'/themes/custom/iq_custom',
    ];

    foreach($themes as $theme){
      $themeFiles = array_keys( iterator_to_array( new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($theme))));
      foreach( $themeFiles as $filename ){
        if( in_array( pathinfo($filename, PATHINFO_EXTENSION) , ['scss', 'rb']) ){
          $fileDest = str_replace( '/themes/custom', '/sites/default/files/styling_profiles/'.$form_state->getValue('id'), $filename );
          $path = pathinfo($fileDest);
          if (!file_exists($path['dirname'])) {
            mkdir($path['dirname'], 0755, true);
          } 
          copy( $filename, $fileDest );
        }
      }
    }


    // write new definitions file
    // quick n dirty!!

    foreach( $styles as $stylingKey => $stylingValaue ){
      if( (strpos($stylingKey, 'opacity') !== false) && empty($stylingValaue)  ){
        $styles[$stylingKey] = 1;
      }
    }

    $definitionContent = file_get_contents($_SERVER["DOCUMENT_ROOT"].'/themes/custom/iq_barrio/resources/sass/_definitions.scss.txt');
    $definitionContent = preg_replace_callback('/\{{(\w+)}}/', function($match) use ($styles){
      $matched = $match[0];
      $name = $match[1];
      return isset($styles[$name]) ? $styles[$name] : $matched;
    }, $definitionContent);

    file_put_contents( $_SERVER["DOCUMENT_ROOT"].'/sites/default/files/styling_profiles/'.$form_state->getValue('id').'/iq_barrio/resources/sass/_definitions.scss' , $definitionContent);

    \Drupal::moduleHandler()->invoke('styling_profiles', 'library_info_build', []);

    // Tell the user we've updated their ball.
    drupal_set_message($this->t('Profile %label has been %action.', ['%label' => $profile->label(), '%action' => $action]));
    $this->logger('sample_config_entity')->notice('Styling profile %label has been %action.', ['%label' => $profile->label(), 'link' => $edit_link]);

    // Redirect back to the list view.
    $form_state->setRedirect('entity.styling_profile.collection');

  }

  /**
   * Checks if a profile machine name is taken.
   *
   * @param string $value
   *   The machine name.
   * @param array $element
   *   An array containing the structure of the 'id' element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether or not the profile machine name is taken.
   */
  public function profileExists($value, array $element, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $profile */
    $profile = $form_state->getFormObject()->getEntity();
    return (bool) $this->entityTypeManager->getStorage($profile->getEntityTypeId())
      ->getQuery()
      ->condition($profile->getEntityType()->getKey('id'), $value)
      ->execute();
  }

}