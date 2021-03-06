<?php
geozzy::load( 'controller/RExtController.php' );

class RExtAccommodationController extends RExtController implements RExtInterface {

  public function __construct( $defRTypeCtrl ){
    parent::__construct( $defRTypeCtrl, new rextAccommodation(), 'rExtAccommodation_' );

    $this->numericFields = array( 'singleRooms', 'doubleRooms', 'familyRooms', 'beds', 'averagePrice' );
  }

  /**
   * Carga los datos de los elementos de la extension
   *
   * @param $resId integer
   *
   * @return array OR false
   */
  public function getRExtData( $resId = false ) {
    $rExtData = false;

    // @todo Esto ten que controlar os idiomas

    if( $resId === false ) {
      $resId = $this->defResCtrl->resObj->getter('id');
    }

    $rExtModel = new AccommodationModel();
    $rExtList = $rExtModel->listItems( array( 'filters' => array( 'resource' => $resId ), 'cache' => $this->cacheQuery ) );
    $rExtObj = $rExtList->fetch();

    if( $rExtObj ) {
      $rExtData = $rExtObj->getAllData( 'onlydata' );

      // Cargo todos los TAX terms del recurso agrupados por idName de Taxgroup
      $termsGroupedIdName = $this->defResCtrl->getTermsInfoByGroupIdName( $resId );
      if( $termsGroupedIdName !== false ) {
        foreach( $this->taxonomies as $tax ) {
          if( isset( $termsGroupedIdName[ $tax[ 'idName' ] ] ) ) {
            $rExtData[ $tax['idName'] ] = $termsGroupedIdName[ $tax[ 'idName' ] ];
          }
        }
      }
    }

    return $rExtData;
  }


  /**
   * Defino la parte de la extension del formulario
   *
   * @param $form FormController
   */
  public function manipulateForm( FormController $form ) {

    $rExtFieldNames = array();

    $fieldsInfo = array(
      'reservationURL' => array(
        'params' => array( 'label' => __( 'Hotel reservation URL' ) ),
        'rules' => array( 'maxlength' => 2000, 'url' => true )
      ),
      'reservationPhone' => array(
        'params' => array( 'label' => __( 'Hotel reservation phone' ) ),
        'rules' => array( 'maxlength' => 20 )
      ),
      'reservationEmail' => array(
        'params' => array( 'label' => __( 'Hotel reservation email' ) ),
        'rules' => array( 'maxlength' => 255, 'email' => true)
      ),
      'singleRooms' => array(
        'params' => array( 'label' => __( 'Hotel single rooms' ) ),
        'rules' => array( 'digits' => true )
      ),
      'doubleRooms' => array(
        'params' => array( 'label' => __( 'Hotel double rooms' ) ),
        'rules' => array( 'digits' => true )
      ),
      'familyRooms' => array(
        'params' => array( 'label' => __( 'Hotel family rooms' ) ),
        'rules' => array( 'digits' => true )
      ),
      'beds' => array(
        'params' => array( 'label' => __( 'Hotel beds' ) ),
        'rules' => array( 'digits' => true )
      ),
      'averagePrice' => array(
        'params' => array( 'label' => __( 'Hotel average price' ) ),
        'rules' => array( 'digits' => true )
      ),
      'accommodationType' => array(
        'params' => array( 'label' => __( 'Accommodation type' ), 'type' => 'select',  'multiple' => true, 'class' => 'cgmMForm-order gzzMultiList',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationType' )
        )
      ),
      'accommodationCategory' => array(
        'params' => array( 'label' => __( 'Accommodation category' ), 'type' => 'select', 'class' => 'gzzSelect2',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationCategory' )
        )
      ),
      'accommodationServices' => array(
        'params' => array( 'label' => __( 'Accommodation services' ), 'type' => 'select', 'multiple' => true, 'class' => 'gzzMultiList',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationServices' )
        )
      ),
      'accommodationFacilities' => array(
        'params' => array( 'label' => __( 'Accommodation facilities' ), 'type' => 'select', 'multiple' => true, 'class' => 'gzzMultiList',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationFacilities' )
        )
      ),
      'accommodationBrand' => array(
        'params' => array( 'label' => __( 'Accommodation brand' ), 'type' => 'select',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationBrand' )
        )
      ),
      'accommodationUsers' => array(
        'params' => array( 'label' => __( 'Accommodation users profile' ), 'type' => 'select',
          'options' => $this->defResCtrl->getOptionsTax( 'accommodationUsers' )
        )
      )
    );

    $form->definitionsToForm( $this->prefixArrayKeys( $fieldsInfo ) );

    // Valadaciones extra
    // $form->setValidationRule( 'hotelName_'.$form->langDefault, 'required' );

    // Si es una edicion, añadimos el ID y cargamos los datos
    $valuesArray = $this->getRExtData( $form->getFieldValue( 'id' ) );
    if( $valuesArray ) {
      $valuesArray = $this->prefixArrayKeys( $valuesArray );
      $form->setField( $this->addPrefix( 'id' ), array( 'type' => 'reserved', 'value' => null ) );

      // Limpiando la informacion de terms para el form
      if( $this->taxonomies ) {
        foreach( $this->taxonomies as $tax ) {
          $taxFieldName = $this->addPrefix( $tax[ 'idName' ] );
          if( isset( $valuesArray[ $taxFieldName ] ) && is_array( $valuesArray[ $taxFieldName ] ) ) {
            $taxFieldValues = array();
            foreach( $valuesArray[ $taxFieldName ] as $value ) {
              $taxFieldValues[] = ( is_array( $value ) ) ? $value[ 'id' ] : $value;
            }
            $valuesArray[ $taxFieldName ] = $taxFieldValues;
          }
        }
      }

      $form->loadArrayValues( $valuesArray );
    }

    // Add RExt info to form
    foreach( $fieldsInfo as $fieldName => $info ) {
      if( isset( $info[ 'translate' ] ) && $info[ 'translate' ] ) {
        $rExtFieldNames = array_merge( $rExtFieldNames, $form->multilangFieldNames( $fieldName ) );
      }
      else {
        $rExtFieldNames[] = $fieldName;
      }
    }

    /*******************************************************************
     * Importante: Guardar la lista de campos del RExt en 'FieldNames' *
     *******************************************************************/
    //$rExtFieldNames[] = 'FieldNames';
    $form->setField( $this->addPrefix( 'FieldNames' ), array( 'type' => 'reserved', 'value' => $rExtFieldNames ) );

    $form->saveToSession();

    return( $rExtFieldNames );
  }


  /**
   * Preparamos los datos para visualizar la parte de la extension del formulario
   *
   * @param $form FormController
   *
   * @return Array $viewBlockInfo{ 'template' => array, 'data' => array, 'dataForm' => array }
   */
  // parent::getFormBlockInfo( $form );


  /**
   * Validaciones extra previas a usar los datos
   *
   * @param $form FormController
   */
  // parent::resFormRevalidate( $form );


  /**
   * Creación-Edición-Borrado de los elementos de la extension
   *
   * @param $form FormController
   * @param $resource ResourceModel
   */
  public function resFormProcess( FormController $form, ResourceModel $resource ) {
    if( !$form->existErrors() ) {
      //$numericFields = array( 'averagePrice', 'capacity' );
      $valuesArray = $this->getRExtFormValues( $form->getValuesArray(), $this->numericFields );

      $valuesArray[ 'resource' ] = $resource->getter( 'id' );

      $rExtModel = new AccommodationModel( $valuesArray );
      if( $rExtModel === false ) {
        $form->addFormError( 'No se ha podido guardar el recurso. (rExtModel)','formError' );
      }
    }

    if( !$form->existErrors() ) {
      foreach( $this->taxonomies as $tax ) {
        $taxFieldName = $this->addPrefix( $tax[ 'idName' ] );
        if( !$form->existErrors() && $form->isFieldDefined( $taxFieldName ) ) {
          $this->defResCtrl->setFormTax( $form, $taxFieldName, $tax[ 'idName' ], $form->getFieldValue( $taxFieldName ), $resource );
        }
      }
    }

    if( !$form->existErrors() ) {
      $saveResult = $rExtModel->save();
      if( $saveResult === false ) {
        $form->addFormError( 'No se ha podido guardar el recurso. (rExtModel)','formError' );
      }
    }
  }


  /**
   * Retoques finales antes de enviar el OK-ERROR a la BBDD y al formulario
   *
   * @param $form FormController
   * @param $resource ResourceModel
   */
  // parent::resFormSuccess( $form, $resource )


  /**
   * Preparamos los datos para visualizar la parte de la extension
   *
   * @return Array $rExtViewBlockInfo{ 'template' => array, 'data' => array }
   */
  public function getViewBlockInfo( $resId = false ) {
    $rExtViewBlockInfo = parent::getViewBlockInfo( $resId );

    if( $rExtViewBlockInfo['data'] ) {
      $rExtViewBlockInfo['template']['full'] = new Template();
      $rExtViewBlockInfo['template']['full']->assign( 'rExt', array( 'data' => $rExtViewBlockInfo['data'] ) );
      $rExtViewBlockInfo['template']['full']->setTpl( 'rExtViewBlock.tpl', 'rextAccommodation' );
    }

    return $rExtViewBlockInfo;
  }

} // class RExtAccommodationController
