<?php

declare(strict_types=1);

namespace Drupal\custom_formatters\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the FormatterSetting content entity.
 */
class FormatterSettingForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);

    $formatter_id = $this->entity->bundle();
    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created %label.', ['%label' => $this->entity->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated %label.', ['%label' => $this->entity->label()]));
    }

    $form_state->setRedirect('entity.formatter.edit_form', ['formatter' => $formatter_id]);

    return $status;
  }

}
