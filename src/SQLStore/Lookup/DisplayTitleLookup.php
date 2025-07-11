<?php

namespace SMW\SQLStore\Lookup;

use RuntimeException;
use SMW\DIProperty;
use SMW\SQLStore\SQLStore;
use SMW\Store;

/**
 * @license GPL-2.0-or-later
 * @since 3.1
 *
 * @author mwjames
 */
class DisplayTitleLookup {
	private const MAX_ITEMS_PER_QUERY = 2000;

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @since 3.1
	 *
	 * @param Store $store
	 */
	public function __construct( Store $store ) {
		$this->store = $store;
	}

	/**
	 * @since 3.1
	 *
	 * @param Iterator|array $dataItems
	 *
	 * @return Iterator|array
	 */
	public function prefetchFromList( array $dataItems ) {
		$list = [];
		$prefetch = [];
		$connection = $this->store->getConnection( 'mw.db' );
		$idTable = $this->store->getObjectIds();
		$hashes = [];

		// Very large queries can fail or confuse the query planner. Avoid this by making
		// multiple smaller queries if necessary.
		$chunks = array_chunk( $dataItems, self::MAX_ITEMS_PER_QUERY, true );

		foreach ( $chunks as $chunk ) {
			// - Doing a select without calling the `CacheWarmer` since this would
			//   cause as circular dependency given that it calls `DisplayTitleFinder`
			//   which itself calls this class
			// - $dataItems[] expects to be of the format [ sha1 => DataItem ]
			// - Adding smw_title, smw_namespace to ensure a `Using index condition`
			//   during the select
			$rows = $connection->select(
				SQLStore::ID_TABLE,
				[
					'smw_id',
					'smw_title',
					'smw_namespace',
					'smw_hash'
				],
				[
					'smw_hash' => array_keys( $chunk )
				],
				__METHOD__
			);

			foreach ( $rows as $row ) {
				$hashes[$row->smw_hash] = $row->smw_id;
			}

			foreach ( $chunk as $sha1 => $dataItem ) {
				$list[$hashes[$sha1] ?? $idTable->getId( $dataItem )] = $dataItem;
			}

			$rows = $this->fetchFromTable( $list );
			if ( !$rows ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( !isset( $list[$row->s_id] ) ) {
					continue;
				}

				$dataItem = $list[$row->s_id];

				if ( $row->o_blob !== null ) {
					$displayTitle = $connection->unescape_bytea( $row->o_blob );
				} else {
					$displayTitle = $row->o_hash;
				}

				unset( $list[$row->s_id] );
				$prefetch[$dataItem->getSha1()] = $displayTitle;
			}
		}

		// Those that don't have a DisplayTitle are marked with a NULL
		foreach ( $list as $id => $dataItem ) {
			$prefetch[$dataItem->getSha1()] = null;
		}

		return $prefetch;
	}

	private function fetchFromTable( $list ) {
		$property = new DIProperty( '_DTITLE' );
		$connection = $this->store->getConnection( 'mw.db' );

		$propTableId = $this->store->findPropertyTableID(
			$property
		);

		$propTables = $this->store->getPropertyTables();

		if ( !isset( $propTables[$propTableId] ) ) {
			throw new RuntimeException( "Missing $propTableId!" );
		}

		$propTable = $propTables[$propTableId];

		$rows = $connection->select(
			$propTable->getName(),
			[
				's_id',
				'o_hash',
				'o_blob'
			],
			[
				's_id' => array_keys( $list )
			],
			__METHOD__
		);

		return $rows;
	}

}
