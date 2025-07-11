<?php

namespace SMW\SQLStore;

use Psr\Log\LoggerAwareTrait;
use SMW\MediaWiki\Connection\Database;
use SMW\SQLStore\Exception\PropertyStatisticsInvalidArgumentException;

/**
 * Simple implementation of PropertyStatisticsTable using MediaWikis
 * database abstraction layer and a single table.
 *
 * @license GPL-2.0-or-later
 * @since 1.9
 *
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Nischay Nahata
 */
class PropertyStatisticsStore {

	use LoggerAwareTrait;

	/**
	 * @var Database
	 */
	private $connection;

	/**
	 * @var bool
	 */
	private $isCommandLineMode = false;

	/**
	 * @var bool
	 */
	private $onTransactionIdle = false;

	/**
	 * @since 1.9
	 *
	 * @param Database $connection
	 */
	public function __construct( Database $connection ) {
		$this->connection = $connection;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:$wgCommandLineMode
	 * Indicates whether MW is running in command-line mode or not.
	 *
	 * @since 2.5
	 *
	 * @param bool $isCommandLineMode
	 */
	public function isCommandLineMode( $isCommandLineMode ) {
		$this->isCommandLineMode = $isCommandLineMode;
	}

	/**
	 * @since 2.5
	 */
	public function waitOnTransactionIdle() {
		$this->onTransactionIdle = !$this->isCommandLineMode;
	}

	/**
	 * Change the usage count for the property of the given ID by the given
	 * value. The method does nothing if the count is 0.
	 *
	 * @since 1.9
	 *
	 * @param int $pid
	 * @param int|array $value
	 *
	 * @return bool Success indicator
	 */
	public function addToUsageCount( $pid, $value ) {
		$usageVal = 0;
		$nullVal = 0;

		if ( is_array( $value ) ) {
			$usageVal = $value[0];
			$nullVal = $value[1];
		} else {
			$usageVal = $value;
		}

		if ( !is_int( $usageVal ) || !is_int( $nullVal ) ) {
			throw new PropertyStatisticsInvalidArgumentException( 'The value to add must be an integer' );
		}

		if ( !is_int( $pid ) || $pid <= 0 ) {
			throw new PropertyStatisticsInvalidArgumentException( 'The property id to add must be a positive integer' );
		}

		if ( $usageVal == 0 && $nullVal == 0 ) {
			return true;
		}

		$this->connection->update(
			SQLStore::PROPERTY_STATISTICS_TABLE,
			[
				$this->safeIncrement( 'usage_count', $usageVal ),
				$this->safeIncrement( 'null_count', $nullVal )
			],
			[
				'p_id' => $pid
			],
			__METHOD__
		);

		return true;
	}

	/**
	 * @since 5.0
	 *
	 * @param string $field
	 * @param int $delta
	 *
	 * @return string
	 */
	private function safeIncrement( string $field, int $delta ) {
		if ( $delta < 0 ) {
			if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
				return $field . '=' . $this->connection->conditional(
					$this->connection->expr( $field, '>=', abs( $delta ) ),
					$field . ' - ' . $this->connection->addQuotes( abs( $delta ) ),
					0
				);
			}

			return $field . '=' . $this->connection->conditional(
				$field . ' >= ' . $this->connection->addQuotes( abs( $delta ) ),
				$field . ' - ' . $this->connection->addQuotes( abs( $delta ) ),
				0
			);
		} else {
			return "$field = $field + " . $this->connection->addQuotes( abs( $delta ) );
		}
	}

	/**
	 * Increase the usage counts of multiple properties.
	 *
	 * The $additions parameter should be an array with integer
	 * keys that are property ids, and associated integer values
	 * that are the amount the usage count should be increased.
	 *
	 * @since 1.9
	 *
	 * @param array $additions
	 *
	 * @return bool Success indicator
	 */
	public function addToUsageCounts( array $additions ) {
		$success = true;

		if ( $additions === [] ) {
			return $success;
		}

		$method = __METHOD__;

		if ( $this->onTransactionIdle ) {
			$this->connection->onTransactionCommitOrIdle( function () use ( $method, $additions ) {
				$this->log( $method . ' (onTransactionIdle)' );
				$this->onTransactionIdle = false;
				$this->addToUsageCounts( $additions );
			} );

			return $success;
		}

		foreach ( $additions as $pid => $addition ) {

			if ( is_array( $addition ) ) {
				// We don't check this, have it fail in case this isn't set correctly
				$addition = [ $addition['usage'], $addition['null'] ];
			}

			$success = $this->addToUsageCount( $pid, $addition ) && $success;
		}

		return $success;
	}

	/**
	 * Adds a new usage count.
	 *
	 * @since 1.9
	 *
	 * @param int $propertyId
	 * @param int $value
	 *
	 * @return bool Success indicator
	 * @throws PropertyStatisticsInvalidArgumentException
	 */
	public function insertUsageCount( $propertyId, $value ) {
		$usageCount = 0;
		$nullCount = 0;

		if ( is_array( $value ) ) {
			$usageCount = $value[0];
			$nullCount = $value[1];
		} else {
			$usageCount = $value;
		}

		if ( !is_int( $usageCount ) || $usageCount < 0 || !is_int( $nullCount ) || $nullCount < 0 ) {
			throw new PropertyStatisticsInvalidArgumentException( 'The value to add must be a positive integer' );
		}

		if ( !is_int( $propertyId ) || $propertyId <= 0 ) {
			throw new PropertyStatisticsInvalidArgumentException( 'The property id to add must be a positive integer' );
		}

		$this->connection->upsert(
			SQLStore::PROPERTY_STATISTICS_TABLE,
			[
				'usage_count' => $usageCount,
				'null_count' => $nullCount,
				'p_id' => $propertyId,
			],
			[ [ 'p_id' ] ],
			[
				'usage_count' => $usageCount,
				'null_count' => $nullCount,
			],
			__METHOD__
		);

		return true;
	}

	/**
	 * Returns the usage count for a provided property id.
	 *
	 * @since 2.2
	 *
	 * @param int $propertyId
	 *
	 * @return int
	 */
	public function getUsageCount( $propertyId ) {
		if ( !is_int( $propertyId ) ) {
			return 0;
		}

		$row = $this->connection->selectRow(
			SQLStore::PROPERTY_STATISTICS_TABLE,
			[
				'usage_count'
			],
			[
				'p_id' => $propertyId,
			],
			__METHOD__
		);

		return $row !== false ? (int)$row->usage_count : 0;
	}

	/**
	 * Returns the usage counts of the provided properties.
	 *
	 * The returned array contains integer keys which are property ids,
	 * with the associated values being their usage count (also integers).
	 *
	 * Properties for which no usage count is found will not have
	 * an entry in the result array.
	 *
	 * @since 1.9
	 *
	 * @param array $propertyIds
	 *
	 * @return array
	 */
	public function getUsageCounts( array $propertyIds ) {
		if ( $propertyIds === [] ) {
			return [];
		}

		$propertyStatistics = $this->connection->select(
			SQLStore::PROPERTY_STATISTICS_TABLE,
			[
				'usage_count',
				'p_id',
			],
			[
				'p_id' => $propertyIds,
			],
			__METHOD__
		);

		$usageCounts = [];

		foreach ( $propertyStatistics as $propertyStatistic ) {
			assert( ctype_digit( $propertyStatistic->p_id ) );
			assert( ctype_digit( $propertyStatistic->usage_count ) );

			$usageCounts[(int)$propertyStatistic->p_id] = (int)$propertyStatistic->usage_count;
		}

		return $usageCounts;
	}

	/**
	 * Deletes all rows in the table.
	 *
	 * @since 1.9
	 *
	 * @return bool Success indicator
	 */
	public function deleteAll() {
		return $this->connection->delete(
			SQLStore::PROPERTY_STATISTICS_TABLE,
			'*',
			__METHOD__
		);
	}

	private function log( $message, $context = [] ) {
		if ( $this->logger === null ) {
			return;
		}

		$this->logger->info( $message, $context );
	}

}
