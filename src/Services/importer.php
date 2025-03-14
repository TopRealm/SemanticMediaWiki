<?php

namespace SMW\Services;

use SMW\Importer\ContentCreators\DispatchingContentCreator;
use SMW\Importer\ContentCreators\TextContentCreator;
use SMW\Importer\ContentCreators\XmlContentCreator;
use SMW\Importer\ContentIterator;
use SMW\Importer\ContentModeller;
use SMW\Importer\Importer;
use SMW\Importer\JsonContentIterator;
use SMW\Importer\JsonImportContentsFileDirReader;
use SMW\Utils\File;
use SMW\Utils\FileFetcher;

/**
 * @codeCoverageIgnore
 *
 * Services defined in this file SHOULD only be accessed either via the
 * ApplicationFactory or a different factory instance.
 *
 * @license GNU GPL v2
 * @since 2.5
 *
 * @author mwjames
 */
return [

	/**
	 * ImporterServiceFactory
	 *
	 * @return callable
	 */
	'ImporterServiceFactory' => static function ( $containerBuilder ) {
		$containerBuilder->registerExpectedReturnType( 'ImporterServiceFactory', '\SMW\Services\ImporterServiceFactory' );
		return new ImporterServiceFactory( $containerBuilder );
	},

	/**
	 * XmlContentCreator
	 *
	 * @return callable
	 */
	'XmlContentCreator' => static function ( $containerBuilder ) {
		$containerBuilder->registerExpectedReturnType( 'XmlContentCreator', '\SMW\Importer\ContentCreators\XmlContentCreator' );
		return new XmlContentCreator( $containerBuilder->create( 'ImporterServiceFactory' ) );
	},

	/**
	 * TextContentCreator
	 *
	 * @return callable
	 */
	'TextContentCreator' => static function ( $containerBuilder ) {
		$containerBuilder->registerExpectedReturnType( 'TextContentCreator', '\SMW\Importer\ContentCreators\TextContentCreator' );

		$connectionManager = $containerBuilder->singleton( 'ConnectionManager' );

		$textContentCreator = new TextContentCreator(
			$containerBuilder->create( 'TitleFactory' ),
			$connectionManager->getConnection( 'mw.db' )
		);

		return $textContentCreator;
	},

	/**
	 * Importer
	 *
	 * @return callable
	 */
	'Importer' => static function ( $containerBuilder, ContentIterator $contentIterator ) {
		$containerBuilder->registerExpectedReturnType( 'Importer', '\SMW\Importer\Importer' );

		$dispatchingContentCreator = new DispatchingContentCreator(
			[
				$containerBuilder->create( 'XmlContentCreator' ),
				$containerBuilder->create( 'TextContentCreator' )
			]
		);

		$importer = new Importer(
			$contentIterator,
			$dispatchingContentCreator
		);

		$importer->setReqVersion(
			$containerBuilder->singleton( 'Settings' )->get( 'smwgImportReqVersion' )
		);

		return $importer;
	},

	/**
	 * JsonContentIterator
	 *
	 * @return callable
	 */
	'JsonContentIterator' => static function ( $containerBuilder, $importFileDirs ) {
		$containerBuilder->registerExpectedReturnType( 'JsonContentIterator', '\SMW\Importer\JsonContentIterator' );

		$jsonImportContentsFileDirReader = new JsonImportContentsFileDirReader(
			new ContentModeller(),
			new FileFetcher(),
			new File(),
			$importFileDirs
		);

		return new JsonContentIterator( $jsonImportContentsFileDirReader );
	},

];
