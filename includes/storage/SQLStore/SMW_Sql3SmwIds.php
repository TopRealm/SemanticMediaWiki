<?php
/**
 * Code to access the smw_ids table in SQLStore3.
 *
 * @file
 * @ingroup SMWStore
 *
 * @author Markus Krötzsch
 */


/**
 * Class to access the smw_ids table in SQLStore3.
 * Provides transparent in-memory caching facilities.
 *
 * @since 1.8
 *
 * @ingroup SMWStore
 */
class SMWSql3SmwIds {

	/// Maximal number of cached property IDs.
	public static $PROP_CACHE_MAX_SIZE = 250;
	/// Maximal number of cached non-property IDs.
	public static $PAGE_CACHE_MAX_SIZE = 500;

	public $selectrow_sort_debug = 0;
	public $selectrow_redi_debug = 0;
	public $prophit_debug = 0;
	public $propmiss_debug = 0;
	public $reghit_debug = 0;
	public $regmiss_debug = 0;
	static public $singleton_debug = null;

	/// Parent SMWSQLStore3.
	public $store;

	/// Cache for property IDs.
	/// @note Tests indicate that it is more memory efficient to have two
	/// arrays (IDs and sortkeys) than to have one array that stores both
	/// values in some data structure (other than a single string).
	protected $prop_ids = array();
	/// Cache for property sortkeys.
	protected $prop_sortkeys = array();
	/// Cache for non-property IDs.
	protected $regular_ids = array();
	/// Cache for non-property sortkeys.
	protected $regular_sortkeys = array();
	
	//protected $subobject_data = array();

	/// Use pre-defined ids for Very Important Properties, avoiding frequent ID lookups for those
	public static $special_ids = array(
		'_TYPE' => 1,
		'_URI'  => 2,
		'_INST' => 4,
		'_UNIT' => 7,
		'_IMPO' => 8,
		'_CONV' => 12,
		'_SERV' => 13,
		'_PVAL' => 14,
		'_REDI' => 15,
		'_SUBP' => 17,
		'_SUBC' => 18,
		'_CONC' => 19,
		'_SF_DF' => 20, // Semantic Form's default form property
		'_SF_AF' => 21,  // Semantic Form's alternate form property
		'_ERRP' => 22,
// 		'_1' => 23, // properties for encoding (short) lists
// 		'_2' => 24,
// 		'_3' => 25,
// 		'_4' => 26,
// 		'_5' => 27,
		'_LIST' => 28,
		'_MDAT' => 29,
		'_CDAT' => 30,
		'_NEWP' => 31,
		'_LEDT' => 32,
	);

	/**
	 * Constructor.
	 */
	public function __construct( SMWSQLStore3 $store ) {
		$this->store = $store;
		// Yes, this is a hack, but we only use it for convenient debugging:
		self::$singleton_debug = $this;
	}

	/**
	 * Find the numeric ID used for the page of the given title,
	 * namespace, interwiki, and subobject. If $canonical is set to true,
	 * redirects are taken into account to find the canonical alias ID for
	 * the given page. If no such ID exists, 0 is returned. The Call-By-Ref
	 * parameter $sortkey is set to the current sortkey, or to '' if no ID
	 * exists.
	 */
	public function getSMWPageIDandSort( $title, $namespace, $iw, $subobjectName, &$sortkey, $canonical ) {
		global $smwgQEqualitySupport;

		$id = $this->getCachedId( $title, $namespace, $iw, $subobjectName );
		if ( $id !== false ) { // cache hit
			$sortkey = $this->getCachedSortKey( $title, $namespace, $iw, $subobjectName );
		} else { // cache miss
			$db = wfGetDB( DB_SLAVE );
			if ( $iw == SMW_SQL3_SMWREDIIW && $canonical &&
				$smwgQEqualitySupport != SMW_EQ_NONE && $subobjectName === '' ) {
				$id = $this->getRedirectId( $title, $namespace );
				if ( $id != 0 ) {
					$row = $db->selectRow( 'smw_ids', array( 'smw_id', 'smw_sortkey' ),
						'smw_id=' . $db->addQuotes( $id ),
						__METHOD__ );
					if ( $row !== false ) {
						$sortkey = $row->smw_sortkey;
					} else { // inconsistent DB; just recover somehow
						$sortkey = str_replace( '_', ' ', $title );
					}
				} else {
					$sortkey = '';
				}
			} else {
				$row = $db->selectRow( 'smw_ids', array( 'smw_id', 'smw_sortkey' ),
					'smw_title=' . $db->addQuotes( $title ) .
					' AND smw_namespace=' . $db->addQuotes( $namespace ) .
					' AND smw_iw=' . $db->addQuotes( $iw ) .
					' AND smw_subobject=' . $db->addQuotes( $subobjectName ),
					__METHOD__ );
				$this->selectrow_sort_debug++;
// 				if ( $this->selectrow_sort_debug % 100 == 0 ) {
// 					self::debugDumpCacheStats();
// 				}

				if ( $row !== false ) {
					$id = $row->smw_id;
					$sortkey = $row->smw_sortkey;
				} else {
					$id = 0;
					$sortkey = '';
				}
			}
			$this->setCache( $title, $namespace, $iw, $subobjectName, $id, $sortkey );
		}

		if ( $id == 0 && $subobjectName == '' && $iw == '' ) { // could be a redirect; check
			$id = $this->getSMWPageIDandSort( $title, $namespace, SMW_SQL3_SMWREDIIW, $subobjectName, $sortkey, $canonical );
		}

		return $id;
	}

	/**
	 * Convenience method for calling getSMWPageIDandSort without
	 * specifying a sortkey (if not asked for).
	 */
	public function getSMWPageID( $title, $namespace, $iw, $subobjectName, $canonical = true ) {
		$sort = '';
		return $this->getSMWPageIDandSort( $title, $namespace, $iw, $subobjectName, $sort, $canonical );
	}

	/**
	 * Return the ID that a page redirects to. This is only used internally
	 * and it is not cached since the results will affect the smw_ids
	 * cache, which will prevent duplicate queries for the same redirect
	 * anyway.
	 */
	protected function getRedirectId( $title, $namespace ) {
		$db = wfGetDB( DB_SLAVE );
		$row = $db->selectRow( 'smw_fpt_redi', 'o_id',
			array( 's_title' => $title, 's_namespace' => $namespace ), __METHOD__ );
		$this->selectrow_redi_debug++;
		return ( $row === false ) ? 0 : $row->o_id;
	}

	/**
	 * Find the numeric ID used for the page of the given title, namespace,
	 * interwiki, and subobjectName. If $canonical is set to true,
	 * redirects are taken into account to find the canonical alias ID for
	 * the given page. If no such ID exists, a new ID is created and
	 * returned. In any case, the current sortkey is set to the given one
	 * unless $sortkey is empty.
	 *
	 * @note Using this with $canonical==false can make sense, especially when
	 * the title is a redirect target (we do not want chains of redirects).
	 * But it is of no relevance if the title does not have an id yet.
	 */
	public function makeSMWPageID( $title, $namespace, $iw, $subobjectName, $canonical = true, $sortkey = '' ) {
		$id = $this->getSMWPageIDandSort( $title, $namespace, $iw, $subobjectName, $oldsort, $canonical );

		if ( $id == 0 ) {
			$db = wfGetDB( DB_MASTER );
			$sortkey = $sortkey ? $sortkey : ( str_replace( '_', ' ', $title ) );

			$db->insert(
				'smw_ids',
				array(
					'smw_id' => $db->nextSequenceValue( 'smw_ids_smw_id_seq' ),
					'smw_title' => $title,
					'smw_namespace' => $namespace,
					'smw_iw' => $iw,
					'smw_subobject' => $subobjectName,
					'smw_sortkey' => $sortkey
				),
				__METHOD__
			);

			$id = $db->insertId();

			// Properties also need to be in smw_stats
			if( $namespace == SMW_NS_PROPERTY ) {
				$db->insert(
					'smw_stats',
					array(
						'pid' => $id,
						'usage_count' => 0
					),
					__METHOD__
				);
			}
			$this->setCache( $title, $namespace, $iw, $subobjectName, $id, $sortkey );
		} elseif ( ( $sortkey !== '' ) && ( $sortkey != $oldsort ) ) {
			$db = wfGetDB( DB_MASTER );
			$db->update( 'smw_ids', array( 'smw_sortkey' => $sortkey ), array( 'smw_id' => $id ), __METHOD__ );
			$this->setCache( $title, $namespace, $iw, $subobjectName, $id, $sortkey );
		}

		return $id;
	}

	/**
	 * Properties have a mechanisms for being predefined (i.e. in PHP instead
	 * of in wiki). Special "interwiki" prefixes separate the ids of such
	 * predefined properties from the ids for the current pages (which may,
	 * e.g., be moved, while the predefined object is not movable).
	 */
	public function getPropertyInterwiki( SMWDIProperty $property ) {
		return ( $property->getLabel() !== '' ) ? '' : SMW_SQL3_SMWINTDEFIW;
	}

	/**
	 * This function does the same as getSMWPageID() but takes into account
	 * that properties might be predefined.
	 */
	public function getSMWPropertyID( SMWDIProperty $property ) {
		if ( ( !$property->isUserDefined() ) && ( array_key_exists( $property->getKey(), self::$special_ids ) ) ) {
			return self::$special_ids[$property->getKey()]; // very important property with fixed id
		} else {
			return $this->getSMWPageID( $property->getKey(), SMW_NS_PROPERTY, $this->getPropertyInterwiki( $property ), '', true );
		}
	}

	/**
	 * This function does the same as makeSMWPageID() but takes into account
	 * that properties might be predefined.
	 */
	public function makeSMWPropertyID( SMWDIProperty $property ) {
		if ( ( !$property->isUserDefined() ) && ( array_key_exists( $property->getKey(), self::$special_ids ) ) ) {
			return self::$special_ids[$property->getKey()]; // very important property with fixed id
		} else {
			return $this->makeSMWPageID( $property->getKey(), SMW_NS_PROPERTY,
				$this->getPropertyInterwiki( $property ), '', true, $property->getLabel() );
		}
	}

	/**
	 * Change an internal id to another value. If no target value is given, the
	 * value is changed to become the last id entry (based on the automatic id
	 * increment of the database). Whatever currently occupies this id will be
	 * moved consistently in all relevant tables. Whatever currently occupies
	 * the target id will be ignored (it should be ensured that nothing is
	 * moved to an id that is still in use somewhere).
	 */
	public function moveSMWPageID( $curid, $targetid = 0 ) {
		$db = wfGetDB( DB_MASTER );

		$row = $db->selectRow( 'smw_ids', '*', array( 'smw_id' => $curid ), __METHOD__ );

		if ( $row === false ) return; // no id at current position, ignore

		if ( $targetid == 0 ) { // append new id
			$db->insert(
				'smw_ids',
				array(
					'smw_id' => $db->nextSequenceValue( 'smw_ids_smw_id_seq' ),
					'smw_title' => $row->smw_title,
					'smw_namespace' => $row->smw_namespace,
					'smw_iw' => $row->smw_iw,
					'smw_subobject' => $row->smw_subobject,
					'smw_sortkey' => $row->smw_sortkey
				),
				__METHOD__
			);
			$targetid = $db->insertId();
		} else { // change to given id
			$db->insert( 'smw_ids',
				array( 'smw_id' => $targetid,
					'smw_title' => $row->smw_title,
					'smw_namespace' => $row->smw_namespace,
					'smw_iw' => $row->smw_iw,
					'smw_subobject' => $row->smw_subobject,
					'smw_sortkey' => $row->smw_sortkey
				),
				__METHOD__
			);
		}

		$db->delete( 'smw_ids', array( 'smw_id' => $curid ), 'SMWSQLStore3::moveSMWPageID' );

		$this->setCache( $row->smw_title, $row->smw_namespace, $row->smw_iw,
			$row->smw_subobject, $targetid, $row->smw_sortkey );

		$this->store->changeSMWPageID( $curid, $targetid, $row->smw_namespace, $row->smw_namespace );
	}

	/**
	 * Add or modify a cache entry. The key consists of the
	 * parameters $title, $namespace, $interwiki, and $subobject. The
	 * cached data is $id and $sortkey.
	 */
	public function setCache( $title, $namespace, $interwiki, $subobject, $id, $sortkey ) {
		if ( strpos( $title, ' ' ) !== false ) {
			throw new MWException("Somebody tried to use spaces in a cache title! ($title)");
		}
		if ( $namespace == SMW_NS_PROPERTY && $interwiki == '' && $subobject == '' ) {
			$this->checkPropertySizeLimit();
			$this->prop_ids[$title] = $id;
			$this->prop_sortkeys[$title] = $sortkey;
		} else {
			$hashKey = self::getRegularHashKey( $title, $namespace, $interwiki, $subobject );
			$this->checkRegularSizeLimit();
			$this->regular_ids[$hashKey] = $id;
			$this->regular_sortkeys[$hashKey] = $sortkey;
		}
		if ( $interwiki == SMW_SQL3_SMWREDIIW ) { // speed up detection of redirects when fetching IDs
			$this->setCache(  $title, $namespace, '', $subobject, 0, '' );
		}
	}

	/**
	 * Get a cached SMW ID, or false if no cache entry is found.
	 */
	protected function getCachedId( $title, $namespace, $interwiki, $subobject ) {
		if ( $namespace == SMW_NS_PROPERTY && $interwiki == '' && $subobject == '' ) {
			if ( array_key_exists( $title, $this->prop_ids ) ) {
				$this->prophit_debug++;
				return $this->prop_ids[$title];
			} else {
				$this->propmiss_debug++;
				return false;
			}
		} else {
			$hashKey = self::getRegularHashKey( $title, $namespace, $interwiki, $subobject );
			if ( array_key_exists( $hashKey, $this->regular_ids ) ) {
				$this->reghit_debug++;
				return $this->regular_ids[$hashKey];
			} else {
				$this->regmiss_debug++;
				return false;
			}
		}
	}

	/**
	 * Get a cached SMW sortkey, or false if no cache entry is found.
	 */
	protected function getCachedSortKey( $title, $namespace, $interwiki, $subobject ) {
		if ( $namespace == SMW_NS_PROPERTY && $interwiki == '' && $subobject == '' ) {
			if ( array_key_exists( $title, $this->prop_sortkeys ) ) {
				return $this->prop_sortkeys[$title];
			} else {
				return false;
			}
		} else {
			$hashKey = self::getRegularHashKey( $title, $namespace, $interwiki, $subobject );
			if ( array_key_exists( $hashKey, $this->regular_sortkeys ) ) {
				return $this->regular_sortkeys[$hashKey];
			} else {
				return false;
			}
		}
	}

	/**
	 * Remove any cache entry for the given data.
	 */
	protected function deleteCache( $title, $namespace, $interwiki, $subobject ) {
		if ( $namespace == SMW_NS_PROPERTY && $interwiki == '' && $subobject == '' ) {
			unset( $this->regular_ids[$title] );
			unset( $this->regular_sortkeys[$title] );
		} else {
			$hashKey = $this->getHashKey( $title, $namespace, $interwiki, $subobject );
			unset( $this->regular_ids[$hashKey] );
			unset( $this->regular_sortkeys[$hashKey] );
		}
	}

	/**
	 * Move all cached information about subobjects.
	 * This method is neither efficient nor very convincing
	 * architecturally; it should be redesigned.
	 */
	public function moveSubobjects( $oldtitle, $oldnamespace, $newtitle, $newnamespace ) {
		// Currently we have no way to change title and namespace across all entries.
		// Best we can do is clear the cache to avoid wrong hits:
		if ( $oldnamespace == SMW_NS_PROPERTY || $newnamespace == SMW_NS_PROPERTY ) {
			$this->prop_ids = array();
			$this->prop_sortkeys = array();
		}
		if ( $oldnamespace != SMW_NS_PROPERTY || $newnamespace != SMW_NS_PROPERTY ) {
			$this->regular_ids = array();
			$this->regular_sortkeys = array();
		}
	}

	/**
	 * Delete all cached information.
	 */
	public function clearCaches() {
		$this->prop_ids = array();
		$this->prop_sortkeys = array();
		$this->regular_ids = array();
		$this->regular_sortkeys = array();
	}

	/**
	 * Ensure that the property ID and sortkey caches have space to insert
	 * at least one more element. If not, some other entries will be unset.
	 */
	protected function checkPropertySizeLimit() {
		if ( count( $this->prop_ids ) >= self::$PROP_CACHE_MAX_SIZE ) {
			$keys = array_rand( $this->prop_ids, 10 );
			foreach ( $keys as $key ) {
				unset( $this->prop_ids[$key] );
				unset( $this->prop_sortkeys[$key] );
			}
		}
	}

	/**
	 * Ensure that the non-property ID and sortkey caches have space to
	 * insert at least one more element. If not, some other entries will be
	 * unset.
	 */
	protected function checkRegularSizeLimit() {
		if ( count( $this->regular_ids ) >= self::$PAGE_CACHE_MAX_SIZE ) {
			$keys = array_rand( $this->regular_ids, 10 );
			foreach ( $keys as $key ) {
				unset( $this->regular_ids[$key] );
				unset( $this->regular_sortkeys[$key] );
			}
		}
	}

	/**
	 * Get the hash key for regular (non-property) pages.
	 */
	protected static function getRegularHashKey( $title, $namespace, $interwiki, $subobject ) {
		return "$title#$namespace#$interwiki#$subobject";
	}

	/**
	 * Simple helper method for debugging cache performance. Prints
	 * statistics about the SMWSql3SmwIds object created last.
	 * The following code can be used in LocalSettings.php to enable
	 * this in a wiki:
	 * 
	 * $wgHooks['SkinAfterContent'][] = 'showCacheStats';
	 * function showCacheStats() {
	 *   SMWSql3SmwIds::debugDumpCacheStats();
	 *   return true;
	 * }
	 */
	public static function debugDumpCacheStats() {
		$that = self::$singleton_debug;
		if ( is_null( $that ) ) return;
		print "Executed {$that->selectrow_sort_debug} selects for sortkeys.\n";
		print "Executed {$that->selectrow_redi_debug} selects for redirects.\n";
		print "Regular cache hits: {$that->reghit_debug} misses: {$that->regmiss_debug}";
		if ( $that->regmiss_debug + $that->reghit_debug > 0 ) {
			print " rate: " . $that->reghit_debug/( $that->regmiss_debug + $that->reghit_debug );
		}
		print " cache size: " . count( $that->regular_ids ) . "\n";
		print "Property cache hits: {$that->prophit_debug} misses: {$that->propmiss_debug}";
		if ( $that->propmiss_debug + $that->prophit_debug > 0 ) {
			print " rate: " . $that->prophit_debug/( $that->propmiss_debug + $that->prophit_debug );
		}
		print " cache size: " . count( $that->prop_ids ) . "\n";
// 		debug_zval_dump($that->prop_ids);
	}

}