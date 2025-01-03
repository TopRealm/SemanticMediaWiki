<?php

namespace SMW\MediaWiki\Search;

use RuntimeException;
use SearchEngine;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMW\MediaWiki\Search\Exception\SearchDatabaseInvalidTypeException;
use SMW\MediaWiki\Search\Exception\SearchEngineInvalidTypeException;
use SMW\MediaWiki\Search\ProfileForm\ProfileForm;
use SMW\Exception\ClassNotFoundException;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GNU GPL v2+
 * @since   3.1
 *
 * @author  mwjames
 */
class SearchEngineFactory {

	/**
	 * @since 3.1
	 *
	 * @param IDatabase $connection
	 *
	 * @return SearchEngine
	 * @throws SearchEngineInvalidTypeException
	 */
	public function newFallbackSearchEngine( IDatabase $connection = null ) {
		$applicationFactory = ApplicationFactory::getInstance();
		$settings = $applicationFactory->getSettings();

		if ( $connection === null ) {
			$connection = $applicationFactory->getConnectionManager()->getConnection( DB_REPLICA );
		}

		$type = $settings->get( 'smwgFallbackSearchType' );
		$defaultSearchEngine = $applicationFactory->create( 'DefaultSearchEngineTypeForDB', $connection );

		$dbLoadBalancer = $applicationFactory->create( 'DBLoadBalancer' );

		if ( is_callable( $type ) ) {
			// #3939
			$fallbackSearchEngine = $type( $dbLoadBalancer );
		} elseif ( $type !== null && $this->isValidSearchDatabaseType( $type ) ) {
			$fallbackSearchEngine = new $type( $dbLoadBalancer );
		} else {
			$fallbackSearchEngine = new $defaultSearchEngine( $dbLoadBalancer );
		}

		if ( !$fallbackSearchEngine instanceof SearchEngine ) {
			throw new SearchEngineInvalidTypeException( "The fallback is not a valid search engine type." );
		}

		return $fallbackSearchEngine;
	}

	/**
	 * @since 3.1
	 *
	 * @param SearchEngine $fallbackSearchEngine
	 *
	 * @return ExtendedSearch
	 */
	public function newExtendedSearch( \SearchEngine $fallbackSearchEngine ) {
		$applicationFactory = ApplicationFactory::getInstance();
		$searchEngineConfig = $applicationFactory->create( 'SearchEngineConfig' );

		$store = $applicationFactory->getStore();

		$extendedSearch = new ExtendedSearch(
			$store,
			$fallbackSearchEngine
		);

		$extendedSearch->setExtraPrefixMap(
			ProfileForm::getPrefixMap( ProfileForm::getFormDefinitions( $store ) )
		);

		$extendedSearch->setSearchableNamespaces(
			$searchEngineConfig->searchableNamespaces()
		);

		return $extendedSearch;
	}

	/**
	 * @param $type
	 */
	private function isValidSearchDatabaseType( $type ) {
		if ( !class_exists( $type ) ) {
			throw new ClassNotFoundException( "$type does not exist." );
		}

		if ( $type === 'SMWSearch' ) {
			throw new SearchEngineInvalidTypeException( 'SMWSearch is not a valid fallback search engine type.' );
		}

		if ( $type !== 'SearchEngine' && !is_subclass_of( $type, 'SearchDatabase' ) ) {
			throw new SearchDatabaseInvalidTypeException( $type );
		}

		return true;
	}

}
