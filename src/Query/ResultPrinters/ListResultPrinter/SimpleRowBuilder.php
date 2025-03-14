<?php

namespace SMW\Query\ResultPrinters\ListResultPrinter;

use Linker;
use SMW\Query\Result\ResultArray;

/**
 * Class SimpleRowBuilder
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author Stephan Gambke
 */
class SimpleRowBuilder extends RowBuilder {

	private $linker;

	/**
	 * @param ResultArray[] $fields
	 *
	 * @param int $rownum
	 *
	 * @return string
	 */
	public function getRowText( array $fields, $rownum = 0 ) {
		$fieldTexts = $this->getFieldTexts( $fields );

		$firstFieldText = array_shift( $fieldTexts );

		if ( $firstFieldText === null ) {
			return '';
		}

		if ( count( $fieldTexts ) > 0 ) {

			$otherFieldsText =
				$this->get( 'other-fields-open' ) .
				implode( $this->get( 'propsep' ), $fieldTexts ) .
				$this->get( 'other-fields-close' );

		} else {
			$otherFieldsText = '';
		}

		return $firstFieldText .
			$otherFieldsText;
	}

	/**
	 * @param string[] $fields
	 *
	 * @return array
	 */
	private function getFieldTexts( array $fields ) {
		$columnNumber = 0;
		$fieldTexts = [];

		foreach ( $fields as $field ) {

			$valuesText = $this->getValueTextsBuilder()->getValuesText( $field, $columnNumber );

			if ( $valuesText !== '' ) {
				$fieldTexts[] =
					$this->get( 'field-open-tag' ) .
					$this->getFieldLabel( $field ) .
					$valuesText .
					$this->get( 'field-close-tag' );
			}

			$columnNumber++;
		}

		return $fieldTexts;
	}

	/**
	 * @param ResultArray $field
	 *
	 * @return string
	 */
	private function getFieldLabel( ResultArray $field ) {
		$showHeaders = $this->get( 'show-headers' );

		if ( $showHeaders === SMW_HEADERS_HIDE || $field->getPrintRequest()->getLabel() === '' ) {
			return '';
		}

		$linker = $showHeaders === SMW_HEADERS_PLAIN ? null : $this->getLinker();

		return $this->get( 'field-label-open-tag' ) .
			$field->getPrintRequest()->getText( SMW_OUTPUT_WIKI, $linker ) .
			$this->get( 'field-label-close-tag' ) .
			$this->get( 'field-label-separator' );
	}

	/**
	 * @return Linker
	 */
	protected function getLinker() {
		return $this->linker;
	}

	/**
	 * @param Linker $linker
	 */
	public function setLinker( Linker $linker ) {
		$this->linker = $linker;
	}

}
