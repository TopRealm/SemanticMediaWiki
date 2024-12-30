<?php

namespace SMW\MediaWiki\Specials\FacetedSearch\Filters;

use SMW\Localizer\MessageLocalizerTrait;
use SMW\MediaWiki\Specials\FacetedSearch\Exception\DefaultValueFilterNotFoundException;
use SMW\Utils\UrlArgs;
use SMW\Utils\TemplateEngine;
use SMW\Schema\SchemaFinder;
use SMW\Schema\SchemaList;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\DataTypeRegistry;
use SMW\DataValueFactory;
use SMWDataItem as DataItem;
use Title;
use Html;

/**
 * @license GNU GPL v2+
 * @since   3.2
 *
 * @author mwjames
 */
class ValueFilter {

	use MessageLocalizerTrait;

	/**
	 * @var TemplateEngine
	 */
	private $templateEngine;

	/**
	 * @var ValueFilterFactory
	 */
	private $valueFilterFactory;

	/**
	 * @var SchemaFinder
	 */
	private $schemaFinder;

	/**
	 * @var UrlArgs
	 */
	private $urlArgs;

	/**
	 * @var []
	 */
	private $params;

	/**
	 * @since 3.2
	 *
	 * @param TemplateEngine $templateEngine
	 * @param ValueFilterFactory $valueFilterFactory
	 * @param SchemaFinder $schemaFinder
	 * @param array $params
	 */
	public function __construct( TemplateEngine $templateEngine, ValueFilterFactory $valueFilterFactory, SchemaFinder $schemaFinder, array $params ) {
		$this->templateEngine = $templateEngine;
		$this->valueFilterFactory = $valueFilterFactory;
		$this->schemaFinder = $schemaFinder;
		$this->params = $params;
	}

	/**
	 * @since 3.2
	 *
	 * @param UrlArgs $urlArgs
	 * @param array $valueFilters
	 *
	 * @return array
	 */
	public function create( UrlArgs $urlArgs, array $valueFilters ): array {
		$cards = [];
		$filters = $valueFilters['filter'] ?? [];

		// Check for those filters that have not been generated by the result but
		// were selected by a user, they should remain in the list so that they
		// can be unselected
		foreach ( $urlArgs->getArray( 'pv', [] ) as $prop => $values ) {
			foreach ( $values as $value ) {
				if ( !isset( $filters[$prop][$value] ) ) {
					$filters[$prop][$value] = 1;
				}
			}
		}

		ksort( $filters );

		foreach ( $filters as $property => $values ) {

			if ( $values === [] || $values === '' ) {
				continue;
			}

			$raw = $valueFilters['raw'][$property] ?? [];
			$escapedValues = [];
			foreach ( $values as $k => $groupId ) {
				// Security measure to prevent XSS attacks
				$escapedValues[htmlspecialchars( $k )] = $groupId;
			}

			$valueFilter = $this->newValueFilter( $property );
			$cards[$property] = $valueFilter->create( $urlArgs, $property, $escapedValues, $raw );
		}

		return $cards;
	}

	private function newValueFilter( $property ) {
		$prop = DIProperty::newFromUserLabel(
			$property
		);

		$schemaList = $this->schemaFinder->newSchemaList(
			$prop,
			new DIProperty( '_PROFILE_SCHEMA' )
		);

		$compartmentIterator = $schemaList->newCompartmentIteratorByKey( 'profile' );

		// No particular property, just match all those that have a profile with
		// a `range_group` definition
		if (
			$this->params['range_group_filter_preference'] === true &&
			$compartmentIterator->has( 'range_group' ) ) {

			return $this->valueFilterFactory->newCheckboxRangeGroupValueFilter(
				$compartmentIterator,
				$this->params
			);
		}

		// Only match particular properties that have a known `range_group` definition
		if (
			is_array( $this->params['range_group_filter_preference'] ) &&
			in_array( $property, $this->params['range_group_filter_preference'] ) &&
			$compartmentIterator->has( 'range_group' ) ) {

			return $this->valueFilterFactory->newCheckboxRangeGroupValueFilter(
				$compartmentIterator,
				$this->params
			);
		}

		$type = $this->getType( $prop );

		if (
			isset( $this->params['filter_type'][$type] ) &&
			$this->params['filter_type'][$type] === 'range_filter' ) {

			return $this->valueFilterFactory->newRangeValueFilter(
				$compartmentIterator,
				$this->params
			);
		}

		if ( $this->params['default_filter'] === 'list_filter' ) {
			return $this->valueFilterFactory->newListValueFilter(
				$this->params
			);
		}

		if ( $this->params['default_filter'] === 'checkbox_filter' ) {
			return $this->valueFilterFactory->newCheckboxValueFilter(
				$this->params
			);
		}

		throw new DefaultValueFilterNotFoundException( $property );
	}

	private function getType( $property ) {
		$type = DataTypeRegistry::getInstance()->getDataItemByType(
			$property->findPropertyValueType()
		);

		switch ( $type ) {
			case DataItem::TYPE_NUMBER:
				return 'TYPE_NUMBER';
				break;
			default:
				return '';
				break;
		}
	}

}
