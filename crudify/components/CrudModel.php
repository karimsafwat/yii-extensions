<?php
class CrudModel extends CActiveRecord
{
  protected $assetPath;

  private $_primaryKey;
  private $_originalKey;

  public $formHints = array();

  public function afterFind()
  {
    $this->_originalKey = parent::getPrimaryKey();
  }

  public function getClassTitle()
  {
    return get_class($this);
  }

  public function getPrimaryKey()
  {
    return $this->_originalKey;
  }

  public function getPkArray()
  {
    $pk = $this->getPrimaryKey();
    is_array($pk) or $pk = array($this->metaData->tableSchema->primaryKey => $pk);
    return $pk;
  }

  public function beforeSave()
  {
    $result = parent::beforeSave();

    if($result) {
      $user_id = Yii::app()->user->getId();
      $now = new CDbExpression('NOW()');

      if($this->isNewRecord) {
        $this->created_by_id = $user_id;
        $this->created_at = $now;
      }

      $this->updated_by_id = $user_id;
      $this->updated_at = $now;
    }

    return $result;
  }

  public function afterSave()
  {
    $this->_originalKey = parent::getPrimaryKey();
    $result = parent::afterSave();
    return $result;
  }

  public function renderProperty($property, $params = array(), $htmlOptions = array()) {
    if(strpos($property, '.') !== false) {
      $parts = preg_split('/\./', $property);
      $relationName = $parts[0];

      if(count($parts) > 2) {
        array_shift($parts);
        return $this->$relationName->renderProperty(join('.', $parts), $params, $htmlOptions);
      }
    } else {
      $relationName = $property;
    }

    $relation = $this->getActiveRelation($relationName);

    if(!empty($relation)) {
      switch(get_class($relation)) {
        case 'CHasOneRelation':
        case 'CBelongsToRelation':
          if(empty($this->$relationName)) {
            echo '-';
          } else {
            $foreignModel = $this->$relationName->model();

            $primaryKey = $foreignModel->metaData->tableSchema->primaryKey;
            $params = $this->$relationName->getPrimaryKey();
            if(is_string($params)) {
              $primaryValue = $params;
              $params = array($primaryKey => $primaryValue);
            }

            $controllerId = Yii::app()->controller->getControllerId($relation->className);

            $label = empty($this->$relationName->name) ? '-' : $this->$relationName->name;
            $url = Yii::app()->controller->createUrl("$controllerId/edit", $params);

            echo CHtml::link($label, $url, $htmlOptions);
          }
          break;
        case 'CHasManyRelation':
        default:
          //$class = get_class($relation);
          //return "<div class=\"error\">Fix {$class} relationship</div>";
      }
      return;
    }

    $primaryKey = $this->metaData->tableSchema->primaryKey;

    switch($property) {
      case $primaryKey:
        $url = $params;
        array_unshift($url, 'edit');
        $url[$primaryKey] = $this->$primaryKey;
        echo CHtml::link($this->$primaryKey, $url, $htmlOptions);
        return;
      case 'password':
        echo '********';
        return;
      case 'email':
        echo empty($this->email) ? '-' : CHtml::link($this->email, 'mailto:'.$this->email, $htmlOptions);
        return;
      default:
        if(preg_match('/uri$/', $property)) {
          echo empty($this->$property) ? '-' : CHtml::link($this->$property, $this->$property, $htmlOptions);
          return;
        } else if(preg_match('/^is_/', $property)) {
          echo empty($this->$property) ? '-' : CHtml::checkBox($property, $this->$property == 1, $htmlOptions);
        } else {
          echo empty($this->$property) ? '-' : $this->$property;
        }
      }
  }

  public function getValidatorsByAttribute($attribute)
  {
    $validators = array();
    foreach($this->validators as $validator) {
      if(in_array($attribute, $validator->attributes)) {
        $validators[] = $validator;
      }
    }
    return $validators;
  }

  public function defaultScope()
  {
    // FIX
    return array();

    $tableName = $this->tableName();
    return array(
      'condition' => "$tableName.deleted_at is null and $tableName.deleted_by_id is null"
    );
  }

  public function filtered($criteria = null)
  {
    empty($criteria) or $this->getDbCriteria()->mergeWith($criteria);

    foreach($this->attributeNames() as $attribute) {
      if(!empty($_GET[$attribute])) {
        $criteria = array(
          'condition' => "$attribute = :$attribute",
          'params' => array(":$attribute" => $_GET[$attribute]),
        );
        $this->getDbCriteria()->mergeWith($criteria);
      }
    }

    return $this;
  }

  public function paged($pagination, $sort)
  {
    $criteria = new CDbCriteria();

    $pagination->applyLimit($criteria);
    $sort->applyOrder($criteria);

    $this->getDbCriteria()->mergeWith($criteria);

    return $this;
  }


  public function options($criteria = array())
  {
    $tableAlias = empty($criteria->alias) ? $this->tableName() : $criteria->alias;
    $dbCriteria = $this->getDbCriteria();
    $dbCriteria->mergeWith(array(
      'select' => "distinct $tableAlias.id, $tableAlias.name",
      'order' => "$tableAlias.name asc",
    ));
    $dbCriteria->mergeWith($criteria);
    return $this;
  }

  public function getFormConfig($id)
  {
    $config = new CrudFormConfig();
    return $config->generate($this, $id);
  }

  public function getFormHints($attribute)
  {
    return isset($this->formHints[$model->scenario]) ? $this->formHints[$model->scenario] : $this->formHints;
  }

  public function getForm($id, $parent = null)
  {
    $form = new CrudForm($this, $id, $parent);

    if($form->submitted('submit'.ucfirst($id))) {
      if($form->validate()) {
        $result = $this->save();
        if($result!==false) {
          throw new FormSuccessException($form);
        }
      }
      throw new FormFailException($form);
    }

    return $form;
  }
}
